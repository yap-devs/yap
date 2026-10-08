package agent

import (
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"sync/atomic"
	"testing"
	"time"

	stats "github.com/v2fly/v2ray-core/v5/app/stats/command"
	"google.golang.org/grpc"
	"google.golang.org/grpc/credentials/insecure"
)

func testManagedCore(t *testing.T) (*Core, Desired, context.Context) {
	t.Helper()
	binary := os.Getenv("YAP_TEST_CORE_BINARY")
	if binary == "" {
		t.Skip("requires patched core")
	}
	ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
	t.Cleanup(cancel)
	core := NewCore(Config{CoreBinary: binary, CoreConfigPath: filepath.Join(t.TempDir(), "core.json"), CoreSocketPath: testCoreSocketPath(t)})
	t.Cleanup(core.Stop)
	d := fixtureDesired()
	d.Users = d.Users[:1]
	d.Routes = d.Routes[:1]
	_, port, _ := net.SplitHostPort(freeAddress(t))
	d.Routes[0].ListenPort, _ = strconv.Atoi(port)
	d.CoreConfig = json.RawMessage(`{"inbounds":[{"listen":"127.0.0.1","tag":"yap-main","protocol":"vmess","streamSettings":{"network":"tcp"}}],"outbounds":[{"protocol":"freedom"}]}`)
	if err := core.Apply(ctx, d); err != nil {
		t.Fatal(err)
	}
	return core, d, ctx
}

func TestManagementAPIUsesPrivateSocketUnreachableBySubscribers(t *testing.T) {
	core, desired, ctx := testManagedCore(t)
	entry, err := os.Lstat(core.config.CoreSocketPath)
	if err != nil || entry.Mode()&os.ModeSocket == 0 || entry.Mode().Perm() != 0600 {
		t.Fatalf("management socket is not private: %v %v", entry, err)
	}
	if _, err = stats.NewStatsServiceClient(core.connection).GetSysStats(ctx, &stats.SysStatsRequest{}); err != nil {
		t.Fatalf("agent management access failed: %v", err)
	}
	client := networkVMessClient(t, os.Getenv("YAP_TEST_CORE_BINARY"), desired.Routes[0].ListenPort, desired.Users[0].UUID)
	origin := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		fmt.Fprint(w, "authorized proxy traffic")
	}))
	defer origin.Close()
	response, err := client.Get(origin.URL)
	if err != nil {
		t.Fatalf("ordinary proxy connection failed: %v", err)
	}
	response.Body.Close()
	transport := client.Transport.(*http.Transport)
	probe, cancel := context.WithTimeout(ctx, 2*time.Second)
	defer cancel()
	// VMess can carry TCP targets, not the agent's Unix socket transport.
	connection, err := grpc.DialContext(probe, "passthrough:///management-probe", grpc.WithTransportCredentials(insecure.NewCredentials()), grpc.WithContextDialer(func(ctx context.Context, target string) (net.Conn, error) {
		return transport.DialContext(ctx, "tcp", net.JoinHostPort(core.config.CoreSocketPath, "1"))
	}), grpc.WithBlock())
	if err == nil {
		defer connection.Close()
		if _, err = stats.NewStatsServiceClient(connection).GetSysStats(probe, &stats.SysStatsRequest{}); err == nil {
			t.Fatal("subscriber reached the private management API")
		}
	}
}

func TestUnauthorizedPanelStopsCoreClearsSavedUsersAndPreservesTraffic(t *testing.T) {
	for _, sampleFailure := range []bool{false, true} {
		t.Run(fmt.Sprintf("sample_failure_%t", sampleFailure), func(t *testing.T) {
			core, desired, ctx := testManagedCore(t)
			store, err := OpenStore(filepath.Join(t.TempDir(), "state.sqlite"))
			if err != nil {
				t.Fatal(err)
			}
			defer store.Close()
			var status atomic.Int32
			status.Store(http.StatusUnauthorized)
			server := httptest.NewTLSServer(http.HandlerFunc(func(w http.ResponseWriter, request *http.Request) {
				if status.Load() != http.StatusOK {
					w.WriteHeader(int(status.Load()))
					return
				}
				if request.Method == http.MethodGet {
					if request.URL.Query().Get("revision") != "0" {
						t.Error("revoked authorization revision was reused")
					}
					json.NewEncoder(w).Encode(desired)
					return
				}
				var batch Batch
				if err := json.NewDecoder(request.Body).Decode(&batch); err != nil {
					t.Error(err)
				}
				json.NewEncoder(w).Encode(map[string]any{"accepted": true, "batch_uuid": batch.UUID})
			}))
			defer server.Close()
			panel := NewPanel(Config{PanelURL: server.URL, Token: "synthetic-node-token"})
			panel.client = server.Client()
			runtime := NewRuntime(store, panel, core)
			if err = runtime.saveDesired(desired); err != nil {
				t.Fatal(err)
			}
			origin := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, request *http.Request) {
				fmt.Fprint(w, strings.Repeat("x", 1000))
			}))
			defer origin.Close()
			client := networkVMessClient(t, os.Getenv("YAP_TEST_CORE_BINARY"), desired.Routes[0].ListenPort, desired.Users[0].UUID)
			response, err := client.Get(origin.URL)
			if err != nil {
				t.Fatal(err)
			}
			io.Copy(io.Discard, response.Body)
			response.Body.Close()
			if sampleFailure {
				if err = runtime.sample(ctx); err != nil {
					t.Fatal(err)
				}
				core.connection.Close()
				core.connection = nil
			}
			if err = runtime.poll(ctx); !errors.Is(err, ErrPanelUnauthorized) {
				t.Fatalf("expected explicit authorization revocation: %v", err)
			}
			saved, err := store.Desired()
			if err != nil || core.Running() || runtime.desired.Revision != 0 || saved.Revision != 0 || len(saved.Users) != 0 {
				t.Fatalf("revoked authorization survived: %+v %v", saved, err)
			}
			if response, err = client.Get(origin.URL); err == nil {
				response.Body.Close()
				t.Fatal("disabled node still accepts new VMess connections")
			}
			pending, err := store.Pending()
			if err != nil || len(pending) == 0 {
				t.Fatalf("traffic was discarded when authorization was revoked: %v", err)
			}
			if err = runtime.traffic(ctx); err == nil {
				t.Fatal("disabled panel unexpectedly accepted traffic")
			}
			retry, err := store.Pending()
			if err != nil || string(retry) != string(pending) {
				t.Fatal("rejected traffic changed or was discarded")
			}
			// A restart during an outage must not restore the revoked snapshot.
			status.Store(http.StatusServiceUnavailable)
			restarted := NewRuntime(store, panel, NewCore(core.config))
			restartContext, cancel := context.WithTimeout(ctx, 200*time.Millisecond)
			err = restarted.Run(restartContext)
			cancel()
			if err != nil || restarted.core.Generation() != "" {
				t.Fatalf("restart restored revoked users: %v", err)
			}
			// Re-enabling the node requires a fresh panel snapshot and permits the saved receipt to drain.
			status.Store(http.StatusOK)
			if err = runtime.poll(ctx); err != nil || !core.Running() {
				t.Fatalf("node did not recover after re-enabling: %v", err)
			}
			if err = runtime.traffic(ctx); err != nil {
				t.Fatal(err)
			}
			if pending, err = store.Pending(); err != nil || pending != nil {
				t.Fatal("saved traffic did not drain after re-enabling")
			}
		})
	}
}

func TestTransientPanelFailurePreservesAppliedAuthorization(t *testing.T) {
	core, desired, ctx := testManagedCore(t)
	store, err := OpenStore(filepath.Join(t.TempDir(), "state.sqlite"))
	if err != nil {
		t.Fatal(err)
	}
	defer store.Close()
	server := httptest.NewTLSServer(http.HandlerFunc(func(w http.ResponseWriter, request *http.Request) {
		w.WriteHeader(http.StatusServiceUnavailable)
	}))
	defer server.Close()
	panel := NewPanel(Config{PanelURL: server.URL, Token: "synthetic-node-token"})
	panel.client = server.Client()
	runtime := NewRuntime(store, panel, core)
	if err = runtime.saveDesired(desired); err != nil {
		t.Fatal(err)
	}
	if err = runtime.poll(ctx); err == nil || errors.Is(err, ErrPanelUnauthorized) {
		t.Fatal("temporary panel failure was treated as authorization revocation")
	}
	saved, err := store.Desired()
	if err != nil || !core.Running() || len(saved.Users) != len(desired.Users) || saved.Revision != desired.Revision {
		t.Fatal("temporary panel failure cleared applied authorization")
	}
}

func TestPrivateManagementSocketRecoversAfterCoreCrash(t *testing.T) {
	core, desired, ctx := testManagedCore(t)
	if err := core.process.Process.Kill(); err != nil {
		t.Fatal(err)
	}
	deadline := time.Now().Add(3 * time.Second)
	for core.Running() {
		if time.Now().After(deadline) {
			t.Fatal("core did not exit")
		}
		time.Sleep(10 * time.Millisecond)
	}
	if err := core.Apply(ctx, desired); err != nil {
		t.Fatalf("stale socket prevented crash recovery: %v", err)
	}
	if _, err := stats.NewStatsServiceClient(core.connection).GetSysStats(ctx, &stats.SysStatsRequest{}); err != nil {
		t.Fatal(err)
	}
}

func TestManagementSocketPreparationPreservesFilesAndActiveListeners(t *testing.T) {
	t.Run("ordinary file", func(t *testing.T) {
		path := testCoreSocketPath(t)
		if err := os.WriteFile(path, []byte("keep-private-file"), 0600); err != nil {
			t.Fatal(err)
		}
		if err := prepareCoreSocket(path); err == nil {
			t.Fatal("ordinary file was accepted as a socket")
		}
		body, err := os.ReadFile(path)
		if err != nil || string(body) != "keep-private-file" {
			t.Fatal("ordinary file was changed or removed")
		}
	})
	t.Run("active socket", func(t *testing.T) {
		path := testCoreSocketPath(t)
		listener, err := net.Listen("unix", path)
		if err != nil {
			t.Fatal(err)
		}
		defer listener.Close()
		if err = prepareCoreSocket(path); err == nil {
			t.Fatal("active socket was removed")
		}
		connection, err := net.Dial("unix", path)
		if err != nil {
			t.Fatal("active socket became inaccessible")
		}
		connection.Close()
	})
	t.Run("public directory", func(t *testing.T) {
		path := testCoreSocketPath(t)
		if err := os.Chmod(filepath.Dir(path), 0755); err != nil {
			t.Fatal(err)
		}
		if err := prepareCoreSocket(path); err == nil {
			t.Fatal("public socket directory was accepted")
		}
	})
}
