package agent

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"net"
	"os"
	"os/exec"
	"path/filepath"
	"strconv"
	"strings"
	"testing"
	"time"
)

func fixtureDesired() Desired {
	return Desired{Revision: 1, ValidUntil: time.Now().Add(time.Hour).Unix(), CoreConfig: json.RawMessage(`{"inbounds":[{"tag":"yap-main","protocol":"vmess","streamSettings":{"network":"tcp"}}],"outbounds":[{"protocol":"freedom"}]}`), Routes: []Route{{ID: 1, InboundTag: "yap-main", ListenPort: 20001}, {ID: 2, InboundTag: "yap-main", ListenPort: 20002}}, Users: []User{{ID: 7, UUID: "c2cda8b8-6c14-4c08-aef0-560f4102a819", Email: "user-7"}, {ID: 8, UUID: "c2cda8b8-6c14-4c08-aef0-560f4102a820", Email: "user-8", LowPriority: true}}}
}
func TestRenderSharedHandlerAndPriority(t *testing.T) {
	d := fixtureDesired()
	b, e := RenderConfig(d, "/var/lib/yap-agent/api.sock")
	if e != nil {
		t.Fatal(e)
	}
	var result map[string]any
	json.Unmarshal(b, &result)
	inbounds := result["inbounds"].([]any)
	if len(inbounds) != 2 {
		t.Fatalf("expected one shared handler plus API, got %d", len(inbounds))
	}
	main := inbounds[0].(map[string]any)
	if main["port"] != "20001-20002" {
		t.Fatal(main)
	}
	clients := main["settings"].(map[string]any)["clients"].([]any)
	if len(clients) != 1 || clients[0].(map[string]any)["email"] != "user-7" {
		t.Fatal(clients)
	}
	api := inbounds[1].(map[string]any)
	if api["listen"] != "/var/lib/yap-agent/api.sock,0600" || api["port"] != nil {
		t.Fatal(api)
	}
	if strings.Contains(string(b), "user-7-port") {
		t.Fatal("authentication identity modified")
	}
	d.Routes[0].ForLowPriority = true
	if _, e = RenderConfig(d, "/var/lib/yap-agent/api.sock"); e == nil {
		t.Fatal("mixed authorization accepted")
	}
	d.Routes[1].ForLowPriority = true
	b, e = RenderConfig(d, "/var/lib/yap-agent/api.sock")
	if e != nil {
		t.Fatal(e)
	}
	if !strings.Contains(string(b), "user-8") {
		t.Fatal("eligible low priority user omitted")
	}
}
func TestRenderRejectsInvalidConfiguration(t *testing.T) {
	for _, modify := range []func(*Desired){func(d *Desired) { d.ValidUntil = 0 }, func(d *Desired) { d.Routes[1].ListenPort = d.Routes[0].ListenPort }, func(d *Desired) { d.Users[0].Email = "wrong" }, func(d *Desired) { d.Routes[0].InboundTag = "native" }, func(d *Desired) { d.Users[0].UUID = "broken" }} {
		d := fixtureDesired()
		modify(&d)
		if _, e := RenderConfig(d, "/var/lib/yap-agent/api.sock"); e == nil {
			t.Fatal("invalid config accepted")
		}
	}
}
func TestRenderRejectsUUIDCaseVariants(t *testing.T) {
	for _, uppercaseFirst := range []bool{false, true} {
		d := fixtureDesired()
		d.Users[1].LowPriority = false
		d.Users[1].UUID = strings.ToUpper(d.Users[0].UUID)
		if uppercaseFirst {
			d.Users[0].UUID, d.Users[1].UUID = d.Users[1].UUID, d.Users[0].UUID
		}
		if _, err := RenderConfig(d, "/var/lib/yap-agent/api.sock"); err == nil {
			t.Fatal("accepted the same UUID under different casing")
		}
	}
}

func TestRenderAcceptsDistinctUppercaseUUID(t *testing.T) {
	d := fixtureDesired()
	d.Users[0].UUID = strings.ToUpper(d.Users[0].UUID)
	if _, err := RenderConfig(d, "/var/lib/yap-agent/api.sock"); err != nil {
		t.Fatalf("rejected a valid distinct uppercase UUID: %v", err)
	}
}

func TestPortCounters(t *testing.T) {
	routes := fixtureDesired().Routes
	values := map[string]int64{"user>>>user-7-port-20001>>>traffic>>>uplink": 12, "user>>>user-7-port-20001>>>traffic>>>downlink": 13, "user>>>user-7-port-20002>>>traffic>>>uplink": 21}
	c, e := ParseCounters(values, routes)
	if e != nil {
		t.Fatal(e)
	}
	if len(c) != 2 || c[0].Uplink != 12 || c[0].RouteID != 1 || c[1].Uplink != 21 || c[1].RouteID != 2 {
		t.Fatal(c)
	}
	values["user>>>user-7>>>traffic>>>uplink"] = 100
	if _, e = ParseCounters(values, routes); e == nil {
		t.Fatal("native aggregate silently accepted")
	}
}

func TestPatchedCoreLifecycle(t *testing.T) {
	binary := os.Getenv("YAP_TEST_CORE_BINARY")
	if binary == "" {
		t.Skip("set YAP_TEST_CORE_BINARY to run patched-core integration")
	}
	address := testCoreSocketPath(t)
	dir := t.TempDir()
	core := NewCore(Config{CoreBinary: binary, CoreConfigPath: filepath.Join(dir, "core.json"), CoreSocketPath: address})
	defer core.Stop()
	ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
	defer cancel()
	if e := core.Check(ctx); e != nil {
		t.Fatal(e)
	}
	d := fixtureDesired()

	for {
		_, p, _ := net.SplitHostPort(freeAddress(t))
		port, _ := strconv.Atoi(p)
		if port >= 65535 {
			continue
		}
		listener, e := net.Listen("tcp", fmt.Sprintf("127.0.0.1:%d", port+1))
		if e != nil {
			continue
		}
		listener.Close()
		d.Routes[0].ListenPort = port
		d.Routes[1].ListenPort = port + 1
		break
	}
	if e := core.Apply(ctx, d); e != nil {
		rendered, _ := RenderConfig(d, address)
		candidate := filepath.Join(dir, "debug.json")
		os.WriteFile(candidate, rendered, 0600)
		output, _ := exec.Command(binary, "test", "-format=json", "-c", candidate).CombinedOutput()
		t.Log(string(output))
		t.Fatal(e)
	}
	generation := core.Generation()
	if !core.Running() {
		t.Fatal("core not running")
	}
	if _, e := core.Counters(ctx); e != nil {
		t.Fatal(e)
	}
	d.Revision++
	d.Users[0].UUID = "c2cda8b8-6c14-4c08-aef0-560f4102a821"
	if e := core.Apply(ctx, d); e != nil {
		t.Fatal(e)
	}
	if generation != core.Generation() {
		t.Fatal("user-only update restarted core")
	}
	d.Users = nil
	d.Revision++
	if e := core.Apply(ctx, d); e != nil {
		t.Fatal(e)
	}
	if generation != core.Generation() {
		t.Fatal("user removal restarted core")
	}
}
func freeAddress(t *testing.T) string {
	t.Helper()
	listener, e := net.Listen("tcp", "127.0.0.1:0")
	if e != nil {
		t.Fatal(e)
	}
	address := listener.Addr().String()
	listener.Close()
	return address
}

func TestPatchedCoreMemory(t *testing.T) {
	binary := os.Getenv("YAP_TEST_CORE_BINARY")
	if binary == "" || os.Getenv("YAP_TEST_MEMORY") == "" {
		t.Skip("set YAP_TEST_CORE_BINARY and YAP_TEST_MEMORY for memory measurement")
	}
	for _, count := range []int{1000, 10000} {
		t.Run(strconv.Itoa(count), func(t *testing.T) {
			dir := t.TempDir()
			apiAddress := testCoreSocketPath(t)
			core := NewCore(Config{CoreBinary: binary, CoreConfigPath: filepath.Join(dir, "core.json"), CoreSocketPath: apiAddress})
			defer core.Stop()
			d := fixtureDesired()
			for {
				_, p, _ := net.SplitHostPort(freeAddress(t))
				port, _ := strconv.Atoi(p)
				if port >= 65535 {
					continue
				}
				listener, e := net.Listen("tcp", fmt.Sprintf("127.0.0.1:%d", port+1))
				if e != nil {
					continue
				}
				listener.Close()
				d.Routes[0].ListenPort = port
				d.Routes[1].ListenPort = port + 1
				break
			}
			d.Users = nil
			for i := 1; i <= count; i++ {
				d.Users = append(d.Users, User{ID: int64(i), UUID: fmt.Sprintf("00000000-0000-4000-8000-%012x", i), Email: fmt.Sprintf("user-%d", i)})
			}
			ctx, cancel := context.WithTimeout(context.Background(), 60*time.Second)
			defer cancel()
			if e := core.Apply(ctx, d); e != nil {
				t.Fatal(e)
			}
			time.Sleep(2 * time.Second)
			status, e := os.ReadFile(fmt.Sprintf("/proc/%d/status", core.process.Process.Pid))
			if e != nil {
				t.Fatal(e)
			}
			for _, line := range strings.Split(string(status), "\n") {
				if strings.HasPrefix(line, "VmRSS:") || strings.HasPrefix(line, "VmHWM:") {
					t.Log(line)
				}
			}
		})
	}
}

func TestManagedTransportMatchesPlainTCPSubscription(t *testing.T) {
	for _, stream := range []string{`{"network":"ws"}`, `{"network":"grpc"}`, `{"network":"tcp","security":"tls"}`, `"invalid"`} {
		d := fixtureDesired()
		d.CoreConfig = json.RawMessage(`{"inbounds":[{"tag":"yap-main","protocol":"vmess","streamSettings":` + stream + `}],"outbounds":[{"protocol":"freedom"}]}`)
		if _, e := RenderConfig(d, "/var/lib/yap-agent/api.sock"); e == nil {
			t.Fatalf("unsupported stream accepted: %s", stream)
		}
	}
	for _, stream := range []string{`{}`, `{"network":"tcp","security":"none"}`} {
		d := fixtureDesired()
		d.CoreConfig = json.RawMessage(`{"inbounds":[{"tag":"yap-main","protocol":"vmess","streamSettings":` + stream + `}],"outbounds":[{"protocol":"freedom"}]}`)
		if _, e := RenderConfig(d, "/var/lib/yap-agent/api.sock"); e != nil {
			t.Fatal(e)
		}
	}
}

func TestPreRestartSampleRunsAfterValidationAndFailureKeepsCore(t *testing.T) {
	binary := os.Getenv("YAP_TEST_CORE_BINARY")
	if binary == "" {
		t.Skip("requires patched core")
	}
	dir := t.TempDir()
	marker := filepath.Join(dir, "validated")
	wrapper := filepath.Join(dir, "core-wrapper")
	script := "#!/bin/sh\nif [ \"$1\" = test ]; then\n \"" + binary + "\" \"$@\" || exit $?\n sleep 0.1\n touch \"" + marker + "\"\n exit 0\nfi\nexec \"" + binary + "\" \"$@\"\n"
	if e := os.WriteFile(wrapper, []byte(script), 0700); e != nil {
		t.Fatal(e)
	}
	core := NewCore(Config{CoreBinary: wrapper, CoreConfigPath: filepath.Join(dir, "core.json"), CoreSocketPath: testCoreSocketPath(t)})
	defer core.Stop()
	d := fixtureDesired()
	d.Routes = nil
	d.Users = nil
	ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
	defer cancel()
	if e := core.Apply(ctx, d); e != nil {
		t.Fatal(e)
	}
	generation := core.Generation()
	os.Remove(marker)
	called := false
	core.BeforeRestart = func(ctx context.Context) error {
		called = true
		if _, e := os.Stat(marker); e != nil {
			t.Fatal("sample invoked before candidate validation finished")
		}
		if core.Generation() != generation || !core.Running() {
			t.Fatal("old generation stopped before sample")
		}
		if _, e := core.Counters(ctx); e != nil {
			t.Fatal(e)
		}
		return errors.New("simulated durable sample failure")
	}
	d.Revision++
	d.CoreConfig = json.RawMessage(`{"inbounds":[],"outbounds":[{"protocol":"freedom"}],"log":{"loglevel":"warning"}}`)
	if e := core.Apply(ctx, d); e == nil {
		t.Fatal("restart ignored failed final sample")
	}
	if !called || !core.Running() || core.Generation() != generation {
		t.Fatal("failed final sample replaced old generation")
	}
	os.Remove(marker)
	core.BeforeRestart = func(ctx context.Context) error {
		if _, e := os.Stat(marker); e != nil {
			t.Fatal("sample preceded validation")
		}
		_, e := core.Counters(ctx)
		return e
	}
	if e := core.Apply(ctx, d); e != nil {
		t.Fatal(e)
	}
	if core.Generation() == generation {
		t.Fatal("successful structural change did not restart")
	}
}

func testCoreSocketPath(t *testing.T) string {
	t.Helper()
	dir, err := os.MkdirTemp("", "yap-core-socket-")
	if err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { os.RemoveAll(dir) })
	return filepath.Join(dir, "api.sock")
}
