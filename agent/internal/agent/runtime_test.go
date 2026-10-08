package agent

import (
	"context"
	"encoding/json"
	"fmt"
	"io"
	"net"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"strconv"
	"testing"
	"time"

	"google.golang.org/grpc"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/credentials/insecure"
	"google.golang.org/grpc/status"
)

func TestRuntimeRestoresOfflineSnapshotAndFlushesPending(t *testing.T) {
	binary := os.Getenv("YAP_TEST_CORE_BINARY")
	if binary == "" {
		t.Skip("set YAP_TEST_CORE_BINARY for runtime integration")
	}
	dir := t.TempDir()
	store, e := OpenStore(filepath.Join(dir, "state.sqlite"))
	if e != nil {
		t.Fatal(e)
	}
	defer store.Close()
	desired := fixtureDesired()
	desired.ValidUntil = time.Now().Add(-time.Hour).Unix()
	desired.Routes = nil
	desired.Users = nil
	if e = store.SaveDesired(desired); e != nil {
		t.Fatal(e)
	}
	if e = store.Sample("previous-generation", []Counter{{1, 1, 5, 6}}); e != nil {
		t.Fatal(e)
	}
	if _, e = store.Pending(); e != nil {
		t.Fatal(e)
	}
	acked := make(chan struct{}, 1)
	polled := make(chan struct{}, 1)
	server := httptest.NewTLSServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Method == http.MethodGet {
			select {
			case polled <- struct{}{}:
			default:
			}
			w.WriteHeader(http.StatusServiceUnavailable)
			return
		}
		var b Batch
		json.NewDecoder(r.Body).Decode(&b)
		json.NewEncoder(w).Encode(map[string]any{"accepted": true, "batch_uuid": b.UUID})
		select {
		case acked <- struct{}{}:
		default:
		}
	}))
	defer server.Close()
	config := Config{PanelURL: server.URL, Token: "test", CoreBinary: binary, CoreConfigPath: filepath.Join(dir, "core.json"), CoreSocketPath: testCoreSocketPath(t)}
	panel := NewPanel(config)
	panel.client = server.Client()
	runtime := NewRuntime(store, panel, NewCore(config))
	ctx, cancel := context.WithCancel(context.Background())
	done := make(chan error, 1)
	go func() { done <- runtime.Run(ctx) }()
	defer func() {
		cancel()
		select {
		case e := <-done:
			if e != nil {
				t.Error(e)
			}
		case <-time.After(15 * time.Second):
			t.Error("runtime failed to stop")
		}
	}()
	for _, ch := range []chan struct{}{acked, polled} {
		select {
		case <-ch:
		case <-time.After(15 * time.Second):
			t.Fatal("runtime request not observed")
		}
	}
	runtime.mu.Lock()
	running := runtime.core.Running()
	runtime.mu.Unlock()
	if !running {
		t.Fatal("offline expired snapshot was not restored")
	}
	deadline := time.Now().Add(time.Second)
	for time.Now().Before(deadline) {
		var count int
		store.db.QueryRow(`SELECT count(*) FROM state WHERE key='outbox'`).Scan(&count)
		if count == 0 {
			return
		}
		time.Sleep(10 * time.Millisecond)
	}
	t.Fatal("acknowledged batch retained")
}

func TestUploadTickDrainsMultipleBoundedBatches(t *testing.T) {
	store, e := OpenStore(filepath.Join(t.TempDir(), "state.sqlite"))
	if e != nil {
		t.Fatal(e)
	}
	defer store.Close()
	counters := make([]Counter, 2001)
	for i := range counters {
		counters[i] = Counter{UserID: int64(i + 1), RouteID: 1, Uplink: 1}
	}
	if e = store.Sample("g", counters); e != nil {
		t.Fatal(e)
	}
	requests := 0
	records := 0
	server := httptest.NewTLSServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		var b Batch
		if e := json.NewDecoder(r.Body).Decode(&b); e != nil {
			t.Error(e)
		}
		if len(b.Records) > 1000 {
			t.Error("oversized batch")
		}
		requests++
		records += len(b.Records)
		json.NewEncoder(w).Encode(map[string]any{"accepted": true, "batch_uuid": b.UUID})
	}))
	defer server.Close()
	panel := NewPanel(Config{PanelURL: server.URL, Token: "test"})
	panel.client = server.Client()
	runtime := NewRuntime(store, panel, NewCore(Config{}))
	if e = runtime.traffic(context.Background()); e != nil {
		t.Fatal(e)
	}
	if requests != 3 || records != 2001 {
		t.Fatalf("requests=%d records=%d", requests, records)
	}
	body, e := store.Pending()
	if e != nil || body != nil {
		t.Fatal("aggregate not drained")
	}
}

func TestSnapshotIntervalsUseDefaultsAndClamp(t *testing.T) {
	for _, tc := range []struct{ poll, traffic, wantPoll, wantTraffic int }{{0, 0, 5, 60}, {12, 120, 12, 120}, {1, 1, 2, 10}, {-1, -1, 2, 10}, {999, 9999, 300, 3600}} {
		p, r := snapshotIntervals(Desired{PollIntervalSeconds: tc.poll, TrafficIntervalSeconds: tc.traffic})
		if p != time.Duration(tc.wantPoll)*time.Second || r != time.Duration(tc.wantTraffic)*time.Second {
			t.Fatalf("unexpected intervals for %+v: %v %v", tc, p, r)
		}
	}
	var d Desired
	if e := json.Unmarshal([]byte(`{"poll_interval_seconds":9,"traffic_interval_seconds":90}`), &d); e != nil {
		t.Fatal(e)
	}
	p, r := snapshotIntervals(d)
	if p != 9*time.Second || r != 90*time.Second {
		t.Fatal("snapshot intervals ignored")
	}
}

func TestStatisticsFailureDoesNotFreezeAuthorization(t *testing.T) {
	binary := os.Getenv("YAP_TEST_CORE_BINARY")
	if binary == "" {
		t.Skip("requires patched core")
	}
	for _, scenario := range []string{"rotation", "removal", "rotation-with-route-change"} {
		t.Run(scenario, func(t *testing.T) {
			dir := t.TempDir()
			store, err := OpenStore(filepath.Join(dir, "state.sqlite"))
			if err != nil {
				t.Fatal(err)
			}
			defer store.Close()
			address := testCoreSocketPath(t)
			_, portText, _ := net.SplitHostPort(freeAddress(t))
			port, _ := strconv.Atoi(portText)
			desired := fixtureDesired()
			desired.Routes = desired.Routes[:1]
			desired.Routes[0].ListenPort = port
			desired.Users = desired.Users[:1]
			core := NewCore(Config{CoreBinary: binary, CoreSocketPath: address, CoreConfigPath: filepath.Join(dir, "core.json")})
			defer core.Stop()
			ctx, cancel := context.WithTimeout(context.Background(), 45*time.Second)
			defer cancel()
			if err = core.Apply(ctx, desired); err != nil {
				t.Fatal(err)
			}
			generation := core.Generation()
			next := networkCloneDesired(t, desired)
			next.Revision++
			if scenario == "removal" {
				next.Users = nil
			} else {
				next.Users[0].UUID = "c2cda8b8-6c14-4c08-aef0-560f4102a821"
			}
			if scenario == "rotation-with-route-change" {
				_, nextPortText, _ := net.SplitHostPort(freeAddress(t))
				nextPort, _ := strconv.Atoi(nextPortText)
				next.Routes[0].ID = 3
				next.Routes[0].ListenPort = nextPort
			}
			panelServer := httptest.NewTLSServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
				json.NewEncoder(w).Encode(next)
			}))
			defer panelServer.Close()
			panel := NewPanel(Config{PanelURL: panelServer.URL, Token: "test"})
			panel.client = panelServer.Client()
			runtime := NewRuntime(store, panel, core)
			runtime.desired = desired
			if err = store.SaveDesired(desired); err != nil {
				t.Fatal(err)
			}
			origin := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) { fmt.Fprint(w, "local traffic") }))
			defer origin.Close()
			fetch := func(client *http.Client) error {
				response, err := client.Get(origin.URL)
				if err != nil {
					return err
				}
				defer response.Body.Close()
				_, err = io.Copy(io.Discard, response.Body)
				return err
			}
			oldClient := networkVMessClient(t, binary, port, desired.Users[0].UUID)
			if err = fetch(oldClient); err != nil {
				t.Fatal(err)
			}
			if err = runtime.sample(ctx); err != nil {
				t.Fatal(err)
			}
			pending, err := store.Pending()
			if err != nil || len(pending) == 0 {
				t.Fatal("initial traffic was not persisted")
			}
			core.connection.Close()
			core.connection, err = grpc.NewClient("unix://"+address, grpc.WithTransportCredentials(insecure.NewCredentials()), grpc.WithUnaryInterceptor(func(ctx context.Context, method string, request, response any, connection *grpc.ClientConn, invoker grpc.UnaryInvoker, options ...grpc.CallOption) error {
				if method == "/v2ray.core.app.stats.command.StatsService/QueryStats" {
					return status.Error(codes.Unavailable, "simulated statistics failure")
				}
				return invoker(ctx, method, request, response, connection, options...)
			}))
			if err != nil {
				t.Fatal(err)
			}
			err = runtime.poll(ctx)
			if scenario == "rotation-with-route-change" {
				if err == nil || runtime.desired.Revision != desired.Revision {
					t.Fatal("structural change incorrectly acknowledged despite failed final sampling")
				}
			} else if err != nil || runtime.desired.Revision != next.Revision {
				t.Fatalf("authorization reconciliation failed: %v", err)
			}
			if core.Generation() != generation {
				t.Fatal("statistics failure restarted the core")
			}
			saved, err := store.Desired()
			if err != nil || len(saved.Users) != len(next.Users) || (len(saved.Users) > 0 && saved.Users[0].UUID != next.Users[0].UUID) {
				t.Fatal("reconciled authorization was not saved durably")
			}
			if err = fetch(oldClient); err == nil {
				t.Fatal("old UUID remains authorized while statistics fail")
			}
			if len(next.Users) > 0 {
				if err = fetch(networkVMessClient(t, binary, port, next.Users[0].UUID)); err != nil {
					t.Fatalf("rotated UUID was not accepted: %v", err)
				}
			}
			retry, err := store.Pending()
			if err != nil || string(retry) != string(pending) {
				t.Fatal("immutable traffic batch changed during authorization reconciliation")
			}
			core.connection.Close()
			core.connection, err = grpc.NewClient("unix://"+address, grpc.WithTransportCredentials(insecure.NewCredentials()))
			if err != nil {
				t.Fatal(err)
			}
			if err = runtime.poll(ctx); err != nil || runtime.desired.Revision != next.Revision {
				t.Fatalf("recovery failed: %v", err)
			}
		})
	}
}
