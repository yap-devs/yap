package agent

import (
	"context"
	"encoding/json"
	"errors"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"testing"
)

func TestPanelTLSConfigAndImmutableRetry(t *testing.T) {
	var bodies []string
	server := httptest.NewTLSServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Header.Get("Authorization") != "Bearer local-secret" {
			t.Error("missing auth")
		}
		if r.Method == http.MethodGet {
			if r.URL.Query().Get("agent_version") != AgentVersion || r.URL.Query().Get("core_version") != CoreVersion {
				t.Error("missing version heartbeat")
			}
			if r.URL.Query().Get("revision") != "3" || r.URL.Query().Get("applied_revision") != "3" {
				t.Error("missing revisions")
			}
			w.WriteHeader(304)
			return
		}
		var b Batch
		if e := json.NewDecoder(r.Body).Decode(&b); e != nil {
			t.Error(e)
		}
		body, _ := json.Marshal(b)
		bodies = append(bodies, string(body))
		json.NewEncoder(w).Encode(map[string]any{"batch_uuid": b.UUID, "accepted": true})
	}))
	defer server.Close()
	p := NewPanel(Config{PanelURL: server.URL, Token: "local-secret"})
	p.client = server.Client()
	d, e := p.Fetch(context.Background(), 3)
	if e != nil || d != nil {
		t.Fatal(d, e)
	}
	body := []byte(`{"batch_uuid":"same-uuid","records":[{"user_id":1,"route_id":2,"uplink":3,"downlink":4}]}`)
	for i := 0; i < 2; i++ {
		id, e := p.Send(context.Background(), body)
		if e != nil || id != "same-uuid" {
			t.Fatal(id, e)
		}
	}
	if bodies[0] != bodies[1] {
		t.Fatal("retry changed payload")
	}
}

func TestPanelDistinguishesAuthorizationRevocationFromServerFailures(t *testing.T) {
	for _, code := range []int{http.StatusUnauthorized, http.StatusForbidden, http.StatusServiceUnavailable} {
		server := httptest.NewTLSServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			w.WriteHeader(code)
		}))
		panel := NewPanel(Config{PanelURL: server.URL, Token: "synthetic-node-token"})
		panel.client = server.Client()
		_, err := panel.Fetch(context.Background(), 1)
		server.Close()
		if err == nil || errors.Is(err, ErrPanelUnauthorized) != (code == http.StatusUnauthorized) {
			t.Fatalf("unexpected authorization outcome for HTTP %d: %v", code, err)
		}
	}
}
func TestConfigRejectsUnsafeLocalSettings(t *testing.T) {
	c := Config{PanelURL: "https://panel.example", Token: "secret", StatePath: "/var/lib/yap/state.sqlite", CoreBinary: "/usr/local/bin/v2ray", CoreConfigPath: "/var/lib/yap/core.json", CoreSocketPath: "/var/lib/yap-agent/api.sock"}
	if e := c.Validate(); e != nil {
		t.Fatal(e)
	}
	path := filepath.Join(t.TempDir(), "config.json")
	b, _ := json.Marshal(c)
	os.WriteFile(path, b, 0644)
	if _, e := LoadConfig(path); e == nil {
		t.Fatal("world-readable secret accepted")
	}
	os.Chmod(path, 0600)
	if _, e := LoadConfig(path); e != nil {
		t.Fatal(e)
	}
	c.PanelURL = "http://panel.example"
	if e := c.Validate(); e == nil {
		t.Fatal("plaintext panel accepted")
	}
	c.PanelURL = "https://panel.example"
	c.CoreSocketPath = "0.0.0.0:10085"
	if e := c.Validate(); e == nil {
		t.Fatal("public gRPC accepted")
	}
}
