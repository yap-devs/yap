package agent

import (
	"context"
	"encoding/binary"
	"encoding/json"
	"fmt"
	"io"
	"net"
	"net/http"
	"net/http/httptest"
	"os"
	"os/exec"
	"path/filepath"
	"strconv"
	"strings"
	"testing"
	"time"
)

// This test deliberately sends real authenticated VMess traffic, not just API calls.
func TestPatchedCoreNetworkAuthorization(t *testing.T) {
	binary := os.Getenv("YAP_TEST_CORE_BINARY")
	if binary == "" {
		t.Skip("set YAP_TEST_CORE_BINARY for real VMess integration")
	}
	ctx, cancel := context.WithTimeout(context.Background(), 60*time.Second)
	defer cancel()
	apiAddress := testCoreSocketPath(t)
	core := NewCore(Config{CoreBinary: binary, CoreConfigPath: filepath.Join(t.TempDir(), "core.json"), CoreSocketPath: apiAddress})
	defer core.Stop()
	d := fixtureDesired()
	d.Users = d.Users[:1]
	for {
		_, p, _ := net.SplitHostPort(freeAddress(t))
		port, _ := strconv.Atoi(p)
		if port >= 65535 {
			continue
		}
		l, e := net.Listen("tcp", fmt.Sprintf("127.0.0.1:%d", port+1))
		if e != nil {
			continue
		}
		l.Close()
		d.Routes[0].ListenPort = port
		d.Routes[1].ListenPort = port + 1
		break
	}
	if e := core.Check(ctx); e != nil {
		t.Fatal(e)
	}
	if e := core.Apply(ctx, d); e != nil {
		t.Fatal(e)
	}
	generation := core.Generation()
	release := make(chan struct{})
	origin := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path == "/stream" {
			w.Header().Set("Content-Length", "2")
			fmt.Fprint(w, "a")
			w.(http.Flusher).Flush()
			select {
			case <-release:
				fmt.Fprint(w, "b")
			case <-r.Context().Done():
			}
			return
		}
		n, _ := strconv.Atoi(strings.TrimPrefix(r.URL.Path, "/"))
		fmt.Fprint(w, strings.Repeat("x", n))
	}))
	defer origin.Close()
	clients := []*http.Client{
		networkVMessClient(t, binary, d.Routes[0].ListenPort, d.Users[0].UUID),
		networkVMessClient(t, binary, d.Routes[1].ListenPort, d.Users[0].UUID),
	}
	fetch := func(client *http.Client, n int) error {
		r, e := client.Get(fmt.Sprintf("%s/%d", origin.URL, n))
		if e != nil {
			return e
		}
		defer r.Body.Close()
		b, e := io.ReadAll(r.Body)
		if e == nil && len(b) != n {
			return fmt.Errorf("got %d bytes, want %d", len(b), n)
		}
		return e
	}
	if e := fetch(clients[0], 1000); e != nil {
		t.Fatal(e)
	}
	first, e := core.Counters(ctx)
	if e != nil {
		t.Fatal(e)
	}
	if len(first) != 1 || first[0].RouteID != d.Routes[0].ID || first[0].Downlink < 1000 {
		t.Fatalf("first port counters: %+v", first)
	}
	if e := fetch(clients[1], 3000); e != nil {
		t.Fatal(e)
	}
	second, e := core.Counters(ctx)
	if e != nil {
		t.Fatal(e)
	}
	found := false
	for _, c := range second {
		if c.RouteID == first[0].RouteID && c != first[0] {
			t.Fatalf("second port changed first: %+v => %+v", first, c)
		}
		if c.RouteID == d.Routes[1].ID && c.Downlink >= 3000 {
			found = true
		}
	}
	if !found {
		t.Fatalf("second port counters missing: %+v", second)
	}
	t.Logf("isolated cumulative counters: %+v", second)
	// Copy the desired snapshot as a new HTTP response would, avoiding shared slices.
	rotated := networkCloneDesired(t, d)
	rotated.Revision++
	rotated.Users[0].UUID = "c2cda8b8-6c14-4c08-aef0-560f4102a821"
	if e := core.Apply(ctx, rotated); e != nil {
		t.Fatal(e)
	}
	if core.Generation() != generation {
		t.Fatal("UUID rotation restarted core")
	}
	if e := fetch(clients[0], 100); e == nil {
		t.Fatal("old UUID still authorized after rotation")
	}
	fresh := networkVMessClient(t, binary, rotated.Routes[0].ListenPort, rotated.Users[0].UUID)
	if e := fetch(fresh, 100); e != nil {
		t.Fatalf("new UUID rejected: %v", e)
	}
	stream_client := *fresh
	stream_client.Timeout = 15 * time.Second
	stream, e := stream_client.Get(origin.URL + "/stream")
	if e != nil {
		t.Fatal(e)
	}
	defer stream.Body.Close()
	one := make([]byte, 1)
	if _, e = io.ReadFull(stream.Body, one); e != nil || one[0] != 'a' {
		t.Fatalf("stream setup: %q %v", one, e)
	}
	removed := networkCloneDesired(t, rotated)
	removed.Revision++
	removed.Users = nil
	if e := core.Apply(ctx, removed); e != nil {
		t.Fatal(e)
	}
	if core.Generation() != generation {
		t.Fatal("user removal restarted core")
	}
	if e := fetch(fresh, 100); e == nil {
		t.Fatal("removed UUID still opens new connections")
	}
	close(release)
	// Native removal is not advertised as established-connection revocation.
	_, streamErr := io.ReadFull(stream.Body, one)
	t.Logf("established connection after removal: byte=%q error=%v", one, streamErr)
	core.Stop()
	if e := core.Apply(ctx, rotated); e != nil {
		t.Fatal(e)
	}
	if core.Generation() == generation {
		t.Fatal("restart did not create a new generation")
	}
	if e := fetch(fresh, 100); e != nil {
		t.Fatalf("saved authorization not restored: %v", e)
	}
	if e := fetch(clients[0], 100); e == nil {
		t.Fatal("restart restored revoked UUID")
	}
}

func networkCloneDesired(t *testing.T, d Desired) Desired {
	t.Helper()
	b, e := json.Marshal(d)
	if e != nil {
		t.Fatal(e)
	}
	var result Desired
	if e = json.Unmarshal(b, &result); e != nil {
		t.Fatal(e)
	}
	return result
}

func networkVMessClient(t *testing.T, binary string, port int, uuid string) *http.Client {
	t.Helper()
	address := freeAddress(t)
	_, p, _ := net.SplitHostPort(address)
	socksPort, _ := strconv.Atoi(p)
	cfg := map[string]any{"log": map[string]any{"loglevel": "none"}, "inbounds": []any{map[string]any{"listen": "127.0.0.1", "port": socksPort, "protocol": "socks", "settings": map[string]any{"auth": "noauth"}}}, "outbounds": []any{map[string]any{"protocol": "vmess", "settings": map[string]any{"vnext": []any{map[string]any{"address": "127.0.0.1", "port": port, "users": []any{map[string]any{"id": uuid, "alterId": 0, "security": "auto"}}}}}}}}
	b, e := json.Marshal(cfg)
	if e != nil {
		t.Fatal(e)
	}
	path := filepath.Join(t.TempDir(), "client.json")
	if e = os.WriteFile(path, b, 0600); e != nil {
		t.Fatal(e)
	}
	cmd := exec.Command(binary, "run", "-format=json", "-c", path)
	if e = cmd.Start(); e != nil {
		t.Fatal(e)
	}
	t.Cleanup(func() { _ = cmd.Process.Kill(); _ = cmd.Wait() })
	deadline := time.Now().Add(5 * time.Second)
	for {
		c, e := net.DialTimeout("tcp", address, 100*time.Millisecond)
		if e == nil {
			c.Close()
			break
		}
		if time.Now().After(deadline) {
			t.Fatal("VMess client did not start")
		}
		time.Sleep(20 * time.Millisecond)
	}
	transport := &http.Transport{DisableKeepAlives: true, DialContext: func(ctx context.Context, network, target string) (net.Conn, error) {
		c, e := (&net.Dialer{}).DialContext(ctx, "tcp", address)
		if e != nil {
			return nil, e
		}
		success := false
		defer func() {
			if !success {
				c.Close()
			}
		}()
		c.SetDeadline(time.Now().Add(4 * time.Second))
		if _, e = c.Write([]byte{5, 1, 0}); e != nil {
			return nil, e
		}
		reply := make([]byte, 2)
		if _, e = io.ReadFull(c, reply); e != nil {
			return nil, e
		}
		if reply[0] != 5 || reply[1] != 0 {
			return nil, fmt.Errorf("SOCKS auth: %v", reply)
		}
		host, portText, e := net.SplitHostPort(target)
		if e != nil {
			return nil, e
		}
		targetPort, _ := strconv.Atoi(portText)
		req := append([]byte{5, 1, 0, 3, byte(len(host))}, []byte(host)...)
		req = binaryAppendPort(req, targetPort)
		if _, e = c.Write(req); e != nil {
			return nil, e
		}
		head := make([]byte, 4)
		if _, e = io.ReadFull(c, head); e != nil {
			return nil, e
		}
		if head[1] != 0 {
			return nil, fmt.Errorf("SOCKS connect: %v", head)
		}
		size := 0
		switch head[3] {
		case 1:
			size = 4
		case 4:
			size = 16
		case 3:
			n := make([]byte, 1)
			if _, e = io.ReadFull(c, n); e != nil {
				return nil, e
			}
			size = int(n[0])
		default:
			return nil, fmt.Errorf("SOCKS address type: %d", head[3])
		}
		if _, e = io.ReadFull(c, make([]byte, size+2)); e != nil {
			return nil, e
		}
		c.SetDeadline(time.Time{})
		success = true
		return c, nil
	}}
	t.Cleanup(transport.CloseIdleConnections)
	return &http.Client{Transport: transport, Timeout: 3 * time.Second}
}

func binaryAppendPort(b []byte, port int) []byte {
	var encoded [2]byte
	binary.BigEndian.PutUint16(encoded[:], uint16(port))
	return append(b, encoded[:]...)
}
