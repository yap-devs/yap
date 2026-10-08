package agent

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"io"
	"net"
	"net/http"
	"net/http/httptest"
	"net/http/httputil"
	"net/url"
	"os"
	"os/exec"
	"path/filepath"
	"strconv"
	"strings"
	"sync"
	"testing"
	"time"
)

// This opt-in test uses the actual Laravel HTTP kernel, temporary SQLite, TLS, and VMess.
func TestLaravelPanelEndToEnd(t *testing.T) {
	root := os.Getenv("YAP_TEST_PANEL_ROOT")
	binary := os.Getenv("YAP_TEST_CORE_BINARY")
	if root == "" || binary == "" {
		t.Skip("set YAP_TEST_PANEL_ROOT and YAP_TEST_CORE_BINARY for real panel integration")
	}
	fixture := filepath.Join(root, "tests", "Fixtures", "AgentPanelRouter.php")
	dir := t.TempDir()
	controlToken := NewUUID() + NewUUID()
	api := testCoreSocketPath(t)
	var port int
	for {
		_, text, _ := net.SplitHostPort(freeAddress(t))
		port, _ = strconv.Atoi(text)
		if port >= 65535 {
			continue
		}
		listener, e := net.Listen("tcp", fmt.Sprintf("127.0.0.1:%d", port+1))
		if e != nil {
			continue
		}
		listener.Close()
		break
	}
	env := append(os.Environ(), "YAP_E2E_DIR="+dir, "YAP_E2E_CONTROL_TOKEN="+controlToken, "YAP_E2E_ROUTE_PORT="+strconv.Itoa(port))
	php := os.Getenv("YAP_TEST_PHP_BINARY")
	if php == "" {
		php = "php"
	}
	init := exec.Command(php, fixture, "init")
	init.Env = env
	init.Dir = root
	if output, e := init.CombinedOutput(); e != nil {
		t.Fatalf("isolated fixture initialization: %v\n%s", e, output)
	}
	address := freeAddress(t)
	server := exec.Command(php, "-S", address, fixture)
	server.Env = env
	server.Dir = root
	logFile, e := os.Create(filepath.Join(dir, "php.log"))
	if e != nil {
		t.Fatal(e)
	}
	defer logFile.Close()
	server.Stdout = logFile
	server.Stderr = logFile
	if e = server.Start(); e != nil {
		t.Fatal(e)
	}
	defer func() {
		_ = server.Process.Kill()
		_ = server.Wait()
		if t.Failed() {
			b, _ := os.ReadFile(logFile.Name())
			t.Log(string(b))
		}
	}()
	backend, _ := url.Parse("http://" + address)
	control := func(path string, data any) map[string]any {
		t.Helper()
		var body []byte
		method := "GET"
		if data != nil {
			method = "POST"
			body, _ = json.Marshal(data)
		}
		req, _ := http.NewRequest(method, backend.String()+"/__test/"+path, bytes.NewReader(body))
		req.Header.Set("Authorization", "Bearer "+controlToken)
		req.Header.Set("Content-Type", "application/json")
		resp, e := (&http.Client{Timeout: 10 * time.Second}).Do(req)
		if e != nil {
			t.Fatal(e)
		}
		defer resp.Body.Close()
		b, _ := io.ReadAll(resp.Body)
		if resp.StatusCode != 200 {
			t.Fatalf("control %s status%d: %s", path, resp.StatusCode, b)
		}
		var state map[string]any
		if e = json.Unmarshal(b, &state); e != nil {
			t.Fatalf("control JSON: %s", b)
		}
		return state
	}
	deadline := time.Now().Add(10 * time.Second)
	for {
		conn, e := net.DialTimeout("tcp", address, 100*time.Millisecond)
		if e == nil {
			conn.Close()
			break
		}
		if time.Now().After(deadline) {
			t.Fatal("fixture server readiness")
		}
		time.Sleep(20 * time.Millisecond)
	}
	proxy := httputil.NewSingleHostReverseProxy(backend)
	loseFirstACK := true
	proxy.ModifyResponse = func(response *http.Response) error {
		if response.Request.URL.Path == "/api/agent/v1/traffic" && response.StatusCode == 200 && loseFirstACK {
			loseFirstACK = false
			response.Body.Close()
			body := []byte(`{"error":"simulated lost acknowledgement after commit"}`)
			response.StatusCode = 503
			response.Status = "503 Service Unavailable"
			response.Body = io.NopCloser(bytes.NewReader(body))
			response.ContentLength = int64(len(body))
			response.Header.Set("Content-Length", strconv.Itoa(len(body)))
		}
		return nil
	}
	var mu sync.Mutex
	var firstBatch []byte
	tls := httptest.NewTLSServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path == "/api/agent/v1/traffic" && r.Method == "POST" {
			b, _ := io.ReadAll(r.Body)
			r.Body = io.NopCloser(bytes.NewReader(b))
			mu.Lock()
			if firstBatch == nil {
				firstBatch = append([]byte(nil), b...)
			}
			mu.Unlock()
		}
		proxy.ServeHTTP(w, r)
	}))
	defer tls.Close()
	panel := NewPanel(Config{PanelURL: tls.URL, Token: "yap-e2e-agent-token-0123456789abcdef0123456789abcdef"})
	panel.client = tls.Client()
	store, e := OpenStore(filepath.Join(dir, "agent.sqlite"))
	if e != nil {
		t.Fatal(e)
	}
	defer store.Close()
	core := NewCore(Config{CoreBinary: binary, CoreConfigPath: filepath.Join(dir, "core.json"), CoreSocketPath: api})
	defer core.Stop()
	runtime := NewRuntime(store, panel, core)
	ctx, cancel := context.WithTimeout(context.Background(), 2*time.Minute)
	defer cancel()
	if e = core.Check(ctx); e != nil {
		t.Fatal(e)
	}
	if e = runtime.poll(ctx); e != nil {
		t.Fatal(e)
	}
	generation := core.Generation()
	if len(runtime.desired.Users) != 1 || runtime.desired.Users[0].ID != 42 {
		t.Fatalf("initial eligible users: %+v", runtime.desired.Users)
	}
	uuid := runtime.desired.Users[0].UUID
	origin := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		n, _ := strconv.Atoi(strings.TrimPrefix(r.URL.Path, "/"))
		w.Header().Set("Content-Length", strconv.Itoa(n))
		_, _ = io.WriteString(w, strings.Repeat("x", n))
	}))
	defer origin.Close()
	clients := []*http.Client{networkVMessClient(t, binary, port, uuid), networkVMessClient(t, binary, port+1, uuid)}
	download := func(client *http.Client, n int) {
		t.Helper()
		resp, e := client.Get(origin.URL + "/" + strconv.Itoa(n))
		if e != nil {
			t.Fatal(e)
		}
		defer resp.Body.Close()
		count, e := io.Copy(io.Discard, resp.Body)
		if e != nil || count != int64(n) {
			t.Fatalf("VMess payload %d %v", count, e)
		}
	}
	subscription := func(uuid string, status int) []byte {
		t.Helper()
		resp, e := tls.Client().Get(tls.URL + "/clash/" + uuid + "/yap.yaml")
		if e != nil {
			t.Fatal(e)
		}
		defer resp.Body.Close()
		b, _ := io.ReadAll(resp.Body)
		if resp.StatusCode != status {
			t.Fatalf("subscription status%d wanted%d: %s", resp.StatusCode, status, b)
		}
		return b
	}
	initialSubscription := subscription(uuid, 200)
	if !bytes.Contains(initialSubscription, []byte("1.25x")) || !bytes.Contains(initialSubscription, []byte("3x")) {
		t.Fatal("initial subscription multipliers missing")
	}
	newUUID := "c2cda8b8-6c14-4c08-aef0-560f4102a820"
	subscription(newUUID, 404)
	download(clients[0], 256*1024)
	download(clients[1], 256*1024)
	if e = runtime.traffic(ctx); e == nil {
		t.Fatal("expected lost ACK to defer durable batch")
	}
	pending, err := store.Pending()
	if err != nil || len(pending) == 0 {
		t.Fatal("lost ACK erased the pending batch")
	}
	state := control("state", nil)
	assertPanelAccounting(t, state)
	mu.Lock()
	replay := append([]byte(nil), firstBatch...)
	mu.Unlock()
	if len(replay) == 0 || !bytes.Equal(pending, replay) {
		t.Fatal("durable retry differs from committed request")
	}
	route := runtime.desired.Routes[0]
	control("rate", map[string]any{"route_id": route.ID, "rate": "2.50"})
	if e = runtime.traffic(ctx); e != nil {
		t.Fatal(e)
	}
	if pending, e = store.Pending(); e != nil || len(pending) != 0 {
		t.Fatal("matching ACK failed to clear durable batch")
	}
	if _, e = panel.Send(ctx, replay); e != nil {
		t.Fatal(e)
	}
	retried := control("state", nil)
	if marshalPanelValue(state["batch_count"]) != marshalPanelValue(retried["batch_count"]) || marshalPanelValue(state["record_count"]) != marshalPanelValue(retried["record_count"]) || marshalPanelValue(state["user"]) != marshalPanelValue(retried["user"]) {
		t.Fatal("duplicate batch changed billing state")
	}
	if e = runtime.poll(ctx); e != nil {
		t.Fatal(e)
	}
	if core.Generation() != generation {
		t.Fatal("rate-only change restarted core")
	}
	if !bytes.Contains(subscription(uuid, 200), []byte("2.5x")) {
		t.Fatal("cached subscription retained old multiplier")
	}
	download(clients[0], 1024*1024)
	if e = runtime.traffic(ctx); e != nil {
		t.Fatal(e)
	}
	depleted := control("state", nil)
	assertPanelAccounting(t, depleted)
	pkg := depleted["package"].(map[string]any)
	if pkg["status"] != "used" || pkg["remaining_traffic"].(float64) != 0 {
		t.Fatalf("package was not consumed: %v", pkg)
	}
	t.Log("actual per-route traffic billed with exact multiplier; package consumed")
	control("expire-package", map[string]any{})
	if e = runtime.poll(ctx); e != nil {
		t.Fatal(e)
	}
	subscription(uuid, 404)
	if len(runtime.desired.Users) != 0 {
		t.Fatal("unpaid user remained authorized")
	}
	resp, e := clients[0].Get(origin.URL + "/16")
	if e == nil {
		resp.Body.Close()
		t.Fatal("unpaid user opened a new VMess connection")
	}
	control("recharge", map[string]any{"user_id": 42, "amount": "1.00"})
	if e = runtime.poll(ctx); e != nil {
		t.Fatal(e)
	}
	if len(runtime.desired.Users) != 1 {
		t.Fatal("recharge failed to restore authorization")
	}
	subscription(uuid, 200)
	download(clients[0], 16)
	control("recharge", map[string]any{"user_id": 43, "amount": "1.00"})
	if e = runtime.poll(ctx); e != nil {
		t.Fatal(e)
	}
	subscription(newUUID, 200)
	newClient := networkVMessClient(t, binary, port, newUUID)
	download(newClient, 16)
	if len(runtime.desired.Users) != 2 {
		t.Fatal("first recharge failed to authorize new user")
	}
	if core.Generation() != generation {
		t.Fatal("user-only updates restarted core")
	}
	if e = runtime.traffic(ctx); e != nil {
		t.Fatal(e)
	}
	control("aggregate", map[string]any{})
	final := control("state", nil)
	assertPanelAccounting(t, final)
	stats := final["stats"].([]any)
	if len(stats) != 2 {
		t.Fatalf("expected one hourly display row per user, got %d", len(stats))
	}
	user := final["user"].(map[string]any)
	var stat map[string]any
	for _, row := range stats {
		candidate := row.(map[string]any)
		if candidate["user_id"].(float64) == 42 {
			stat = candidate
		}
	}
	if stat == nil {
		t.Fatal("missing existing user display stats")
	}
	if user["traffic_uplink"] != stat["traffic_uplink"] || user["traffic_downlink"] != stat["traffic_downlink"] {
		t.Fatal("display stats differ from committed billed traffic")
	}
	if final["payment_count"].(float64) != 2 {
		t.Fatal("real payment fulfillment not executed twice")
	}
	control("aggregate", map[string]any{})
	again := control("state", nil)
	if marshalPanelValue(final["user"]) != marshalPanelValue(again["user"]) || marshalPanelValue(final["stats"]) != marshalPanelValue(again["stats"]) {
		t.Fatal("hourly aggregation charged or aggregated twice")
	}
	newUser := final["new_user"].(map[string]any)
	if newUser["traffic_downlink"].(float64) < 16 {
		t.Fatal("new user traffic was not billed")
	}
	t.Log("lost ACK retry billed once; recharge restores old/new users; actual traffic and hourly aggregation verified")
}
func marshalPanelValue(value any) string { b, _ := json.Marshal(value); return string(b) }
func assertPanelAccounting(t *testing.T, state map[string]any) {
	t.Helper()
	user, ok := state["user"].(map[string]any)
	if !ok {
		t.Fatal("missing panel user")
	}
	rows, ok := state["records"].([]any)
	if !ok || len(rows) == 0 {
		t.Fatal("missing committed traffic records")
	}
	var up, down int64
	for _, v := range rows {
		r := v.(map[string]any)
		if int64(r["user_id"].(float64)) != 42 {
			continue
		}
		parts := strings.Split(r["applied_rate"].(string), ".")
		whole, _ := strconv.ParseInt(parts[0], 10, 64)
		var fraction int64
		if len(parts) > 1 {
			fraction, _ = strconv.ParseInt((parts[1] + "00")[:2], 10, 64)
		}
		rate := whole*100 + fraction
		billedUp := int64(r["raw_uplink"].(float64)) * rate / 100
		billedDown := int64(r["raw_downlink"].(float64)) * rate / 100
		if billedUp != int64(r["billed_uplink"].(float64)) || billedDown != int64(r["billed_downlink"].(float64)) {
			t.Fatalf("wrong multiplier in record: %v", r)
		}
		up += billedUp
		down += billedDown
	}
	if up != int64(user["traffic_uplink"].(float64)) || down != int64(user["traffic_downlink"].(float64)) {
		t.Fatal("user totals differ from committed per-route billing")
	}
	pkg := state["package"].(map[string]any)
	remaining := int64(pkg["remaining_traffic"].(float64))
	expectedUnpaid := up + down - (2097152 - remaining)
	if expectedUnpaid < 0 {
		expectedUnpaid = 0
	}
	if int64(user["traffic_unpaid"].(float64)) != expectedUnpaid {
		t.Fatal("unpaid balance differs from package consumption")
	}
}
