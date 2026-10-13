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
	"strings"
	"testing"
	"time"
)

func captureCoreOutput(t *testing.T) (string, string) {
	t.Helper()
	dir := t.TempDir()
	stdoutPath, stderrPath := filepath.Join(dir, "stdout"), filepath.Join(dir, "stderr")
	stdout, err := os.Create(stdoutPath)
	if err != nil {
		t.Fatal(err)
	}
	stderr, err := os.Create(stderrPath)
	if err != nil {
		stdout.Close()
		t.Fatal(err)
	}
	originalStdout, originalStderr := os.Stdout, os.Stderr
	os.Stdout, os.Stderr = stdout, stderr
	t.Cleanup(func() {
		os.Stdout, os.Stderr = originalStdout, originalStderr
		stdout.Close()
		stderr.Close()
	})
	return stdoutPath, stderrPath
}

func readCoreOutput(t *testing.T, path string) string {
	t.Helper()
	b, err := os.ReadFile(path)
	if err != nil {
		t.Fatal(err)
	}
	return string(b)
}

func TestCoreCandidateFailureForwardsOriginalOutput(t *testing.T) {
	stdout, stderr := captureCoreOutput(t)
	dir := t.TempDir()
	binary := filepath.Join(dir, "core")
	if err := os.WriteFile(binary, []byte("#!/bin/sh\nprintf 'raw stdout\\n'\nprintf 'raw stderr\\n' >&2\nexit 1\n"), 0700); err != nil {
		t.Fatal(err)
	}
	core := NewCore(Config{CoreBinary: binary, CoreConfigPath: filepath.Join(dir, "core.json"), CoreSocketPath: testCoreSocketPath(t)})
	defer core.Stop()
	if err := core.Apply(context.Background(), fixtureDesired()); err == nil {
		t.Fatal("rejected candidate accepted")
	}
	if got := readCoreOutput(t, stdout); got != "raw stdout\n" {
		t.Fatalf("stdout changed: %q", got)
	}
	if got := readCoreOutput(t, stderr); got != "raw stderr\n" {
		t.Fatalf("stderr changed: %q", got)
	}
}

func TestRenderEnablesManagedCoreLogs(t *testing.T) {
	for _, settings := range []string{`{}`, `{"access":"none","error":"/tmp/legacy.log","loglevel":"none"}`, `{"loglevel":"debug"}`} {
		d := fixtureDesired()
		var cfg map[string]any
		json.Unmarshal(d.CoreConfig, &cfg)
		var logs map[string]any
		json.Unmarshal([]byte(settings), &logs)
		cfg["log"] = logs
		d.CoreConfig, _ = json.Marshal(cfg)
		b, err := RenderConfig(d, "/var/lib/yap-agent/api.sock")
		if err != nil {
			t.Fatal(err)
		}
		json.Unmarshal(b, &cfg)
		logs = cfg["log"].(map[string]any)
		expected := "warning"
		if settings == `{"loglevel":"debug"}` {
			expected = "debug"
		}
		if logs["access"] != "" || logs["error"] != "" || logs["loglevel"] != expected {
			t.Fatalf("managed logs not enabled: %v", logs)
		}
	}
}

func TestPatchedCoreForwardsNativeLogsAcrossRestart(t *testing.T) {
	binary := os.Getenv("YAP_TEST_CORE_BINARY")
	if binary == "" {
		t.Skip("requires patched core")
	}
	stdout, stderr := captureCoreOutput(t)
	dir := t.TempDir()
	wrapper := filepath.Join(dir, "core-wrapper")
	script := "#!/bin/sh\nif [ \"$1\" = run ]; then\n printf 'original run stdout\\n'\n printf 'original run stderr\\n' >&2\nfi\nexec \"$YAP_TEST_CORE_BINARY\" \"$@\"\n"
	if err := os.WriteFile(wrapper, []byte(script), 0700); err != nil {
		t.Fatal(err)
	}
	core := NewCore(Config{CoreBinary: wrapper, CoreConfigPath: filepath.Join(dir, "core.json"), CoreSocketPath: testCoreSocketPath(t)})
	defer core.Stop()
	d := fixtureDesired()
	d.Routes = d.Routes[:1]
	d.Users = d.Users[:1]
	_, port, _ := net.SplitHostPort(freeAddress(t))
	d.Routes[0].ListenPort, _ = strconv.Atoi(port)
	ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
	defer cancel()
	if err := core.Apply(ctx, d); err != nil {
		t.Fatal(err)
	}
	generation := core.Generation()
	origin := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) { fmt.Fprint(w, "payload") }))
	defer origin.Close()
	client := networkVMessClient(t, binary, d.Routes[0].ListenPort, d.Users[0].UUID)
	for i := 0; i < 10; i++ {
		response, err := client.Get(origin.URL)
		if err != nil {
			t.Fatal(err)
		}
		b, err := io.ReadAll(response.Body)
		response.Body.Close()
		if err != nil || string(b) != "payload" {
			t.Fatalf("traffic failed: %q %v", b, err)
		}
	}
	if generation != core.Generation() || !core.Running() {
		t.Fatal("logging restarted core")
	}
	if _, err := core.Counters(ctx); err != nil {
		t.Fatal(err)
	}
	core.Stop()
	logs := readCoreOutput(t, stdout)
	errorLogs := readCoreOutput(t, stderr)
	if !strings.Contains(logs, "accepted tcp:") || !strings.Contains(logs, "email: user-7") {
		t.Fatalf("missing native access records: %s", logs)
	}
	if !strings.Contains(logs, "original run stdout\n") || !strings.Contains(errorLogs, "original run stderr\n") {
		t.Fatalf("run streams discarded: %s", logs)
	}
	if err := core.Apply(ctx, d); err != nil {
		t.Fatal(err)
	}
	core.Stop()
	logs = readCoreOutput(t, stdout)
	errorLogs = readCoreOutput(t, stderr)
	if strings.Count(logs, "original run stdout\n") != 2 || strings.Count(errorLogs, "original run stderr\n") != 2 {
		t.Fatalf("restart lost previous logs: %s", logs)
	}
}
