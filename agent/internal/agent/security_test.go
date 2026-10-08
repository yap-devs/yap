package agent

import (
	"context"
	"encoding/json"
	"fmt"
	"io"
	"log"
	"net/http"
	"net/http/httptest"
	"path/filepath"
	"strings"
	"sync/atomic"
	"testing"
)

func TestSecurityPanelRejectsUntrustedCertificate(t *testing.T) {
	server := httptest.NewUnstartedServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		t.Error("request reached an untrusted TLS server")
	}))
	server.Config.ErrorLog = log.New(io.Discard, "", 0)
	server.StartTLS()
	defer server.Close()
	panel := NewPanel(Config{PanelURL: server.URL, Token: "private-token"})
	if _, err := panel.Fetch(context.Background(), 0); err == nil || strings.Contains(err.Error(), "private-token") {
		t.Fatalf("untrusted TLS must fail without credentials: %v", err)
	}
}

func TestSecurityPanelDoesNotFollowRedirects(t *testing.T) {
	var leaked atomic.Int32
	target := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		leaked.Add(1)
	}))
	defer target.Close()
	server := httptest.NewTLSServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		http.Redirect(w, r, target.URL, http.StatusTemporaryRedirect)
	}))
	defer server.Close()
	panel := NewPanel(Config{PanelURL: server.URL, Token: "private-token"})
	panel.client.Transport = server.Client().Transport
	if _, err := panel.Fetch(context.Background(), 0); err == nil {
		t.Fatal("redirect accepted as a configuration")
	}
	if _, err := panel.Send(context.Background(), []byte(`{"records":[]}`)); err == nil {
		t.Fatal("redirect accepted as a receipt")
	}
	if leaked.Load() != 0 {
		t.Fatal("credential bearing request followed a redirect")
	}
}

func TestSecurityMalformedAndOversizedPanelResponsesFailClosed(t *testing.T) {
	for _, body := range []string{`{"revision":`, `{"core_config":"` + strings.Repeat("a", (32<<20)+1)} {
		server := httptest.NewTLSServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
			fmt.Fprint(w, body)
		}))
		panel := NewPanel(Config{PanelURL: server.URL, Token: "private-token"})
		panel.client.Transport = server.Client().Transport
		_, err := panel.Fetch(context.Background(), 0)
		server.Close()
		if err == nil {
			t.Fatal("malformed or oversized configuration accepted")
		}
	}
}

func TestSecurityForgedAcknowledgementCannotDiscardPendingTraffic(t *testing.T) {
	store, err := OpenStore(filepath.Join(t.TempDir(), "state.sqlite"))
	if err != nil {
		t.Fatal(err)
	}
	defer store.Close()
	if err = store.Sample("generation", []Counter{{UserID: 7, RouteID: 1, Uplink: 123}}); err != nil {
		t.Fatal(err)
	}
	body, err := store.Pending()
	if err != nil {
		t.Fatal(err)
	}
	if err = store.Ack("foreign-batch"); err == nil {
		t.Fatal("foreign acknowledgement discarded traffic")
	}
	retry, err := store.Pending()
	if err != nil || string(body) != string(retry) {
		t.Fatal("pending batch changed after forged acknowledgement")
	}
}

func TestSecurityCoreAPIUsesPrivateUnixSocketAndUnmanagedInboundsAreRemoved(t *testing.T) {
	desired := fixtureDesired()
	desired.CoreConfig = json.RawMessage(`{"api":{"tag":"public-api","services":["HandlerService"]},"inbounds":[{"tag":"yap-main","protocol":"vmess","settings":{"clients":[{"id":"unmanaged-user"}]}},{"tag":"public-api","listen":"0.0.0.0","port":9999,"protocol":"dokodemo-door"}]}`)
	body, err := RenderConfig(desired, "/var/lib/yap-agent/api.sock")
	if err != nil {
		t.Fatal(err)
	}
	if strings.Contains(string(body), "public-api") || strings.Contains(string(body), "unmanaged-user") {
		t.Fatal("unmanaged API or user survived rendering")
	}
	var config map[string]any
	if err = json.Unmarshal(body, &config); err != nil {
		t.Fatal(err)
	}
	inbounds := config["inbounds"].([]any)
	api := inbounds[len(inbounds)-1].(map[string]any)
	if api["listen"] != "/var/lib/yap-agent/api.sock,0600" || api["tag"] != "yap-api" || api["port"] != nil || api["settings"].(map[string]any)["network"] != "unix" {
		t.Fatal("API was exposed remotely")
	}
	for _, address := range []string{"127.0.0.1:10085", "0.0.0.0:10085", "@abstract-socket", "relative/api.sock", "/tmp/api.sock,0666", "/tmp/../api.sock"} {
		if _, err = RenderConfig(desired, address); err == nil {
			t.Fatalf("nonprivate management socket accepted: %s", address)
		}
	}
}

func TestSecurityCounterIdentityCannotSelectForeignRoutes(t *testing.T) {
	for _, name := range []string{
		"user>>>user-7-port-9999>>>traffic>>>uplink",
		"user>>>user-7-port-20001-port-20002>>>traffic>>>uplink",
		"user>>>user-7-port-20001>>>traffic>>>unknown",
		"user>>>user-0-port-20001>>>traffic>>>uplink",
	} {
		if _, err := ParseCounters(map[string]int64{name: 123}, fixtureDesired().Routes); err == nil {
			t.Fatalf("malformed attribution accepted: %s", name)
		}
	}
}
