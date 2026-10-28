package agent

import (
	"context"
	"crypto/sha256"
	"encoding/json"
	"fmt"
	"io"
	"net"
	"net/http"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"testing"
	"time"

	"github.com/v2fly/v2ray-core/v5/app/router/routercommon"
	"google.golang.org/protobuf/proto"
)

type geositeTransport func(*http.Request) (*http.Response, error)

func (f geositeTransport) RoundTrip(r *http.Request) (*http.Response, error) { return f(r) }

func TestDownloadGeositeChecksChecksum(t *testing.T) {
	body := []byte("candidate geosite data")
	digest := sha256.Sum256(body)
	client := &http.Client{Transport: geositeTransport(func(r *http.Request) (*http.Response, error) {
		content := string(body)
		if r.URL.Path == "/checksum" {
			content = fmt.Sprintf("%x  dlc.dat\n", digest)
		}
		return &http.Response{StatusCode: 200, Body: io.NopCloser(strings.NewReader(content))}, nil
	})}
	got, err := downloadGeosite(context.Background(), client, "https://example.test/data", "https://example.test/checksum")
	if err != nil || string(got) != string(body) {
		t.Fatalf("download failed: %v", err)
	}
}

func TestGeositeWithPatchedCore(t *testing.T) {
	binary := os.Getenv("YAP_TEST_CORE_BINARY")
	if binary == "" {
		t.Skip("set YAP_TEST_CORE_BINARY for core integration")
	}
	dir := t.TempDir()
	asset := func(value string) []byte {
		body, err := proto.Marshal(&routercommon.GeoSiteList{Entry: []*routercommon.GeoSite{{CountryCode: "TEST", Domain: []*routercommon.Domain{{Type: routercommon.Domain_RootDomain, Value: value}}}}})
		if err != nil {
			t.Fatal(err)
		}
		return body
	}
	old, updated := asset("old.example"), asset("new.example")
	if err := os.WriteFile(filepath.Join(dir, "geosite.dat"), old, 0600); err != nil {
		t.Fatal(err)
	}
	socket := testCoreSocketPath(t)
	core := NewCore(Config{CoreBinary: binary, CoreConfigPath: filepath.Join(dir, "core.json"), CoreSocketPath: socket, GeositeAssetDir: dir})
	defer core.Stop()
	desired := fixtureDesired()
	_, p, err := net.SplitHostPort(freeAddress(t))
	if err != nil {
		t.Fatal(err)
	}
	port, err := strconv.Atoi(p)
	if err != nil || port == 65535 {
		t.Skip("unusable test port")
	}
	desired.Routes[0].ListenPort, desired.Routes[1].ListenPort = port, port+1
	var config map[string]any
	if err = json.Unmarshal(desired.CoreConfig, &config); err != nil {
		t.Fatal(err)
	}
	config["routing"] = map[string]any{"rules": []any{map[string]any{"type": "field", "domain": []string{"geosite:test"}, "outboundTag": "direct"}}}
	config["outbounds"] = []any{map[string]any{"protocol": "freedom", "tag": "direct"}}
	desired.CoreConfig, err = json.Marshal(config)
	if err != nil {
		t.Fatal(err)
	}
	ctx, cancel := context.WithTimeout(context.Background(), 45*time.Second)
	defer cancel()
	if err = core.Apply(ctx, desired); err != nil {
		t.Fatal(err)
	}
	generation := core.Generation()
	if err = core.validateGeosite(ctx, []byte("invalid geosite")); err == nil {
		t.Fatal("invalid geosite accepted")
	}
	if !core.Running() || core.Generation() != generation {
		t.Fatal("candidate validation interrupted core")
	}
	if err = core.validateGeosite(ctx, updated); err != nil {
		t.Fatal(err)
	}
	core.BeforeRestart = func(context.Context) error { return fmt.Errorf("sample failed") }
	if err = switchGeosite(dir, updated, func(rollback bool) error {
		if rollback && core.Running() {
			return nil
		}
		return core.apply(ctx, desired, true)
	}); err == nil {
		t.Fatal("sampling failure accepted")
	}
	if !core.Running() || core.Generation() != generation {
		t.Fatal("sampling failure interrupted core")
	}
	if body, err := os.ReadFile(filepath.Join(dir, "geosite.dat")); err != nil || string(body) != string(old) {
		t.Fatal("sampling failure did not restore data")
	}
	core.BeforeRestart = nil
	if err = switchGeosite(dir, updated, func(bool) error { return core.apply(ctx, desired, true) }); err != nil {
		t.Fatal(err)
	}
	if !core.Running() || core.Generation() == generation {
		t.Fatal("new data did not restart core")
	}
}

func TestGeositeRecoveryRestoresPreviousData(t *testing.T) {
	dir := t.TempDir()
	path := filepath.Join(dir, "geosite.dat")
	if err := os.WriteFile(path, []byte("new"), 0600); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(path+".rollback", []byte("old"), 0600); err != nil {
		t.Fatal(err)
	}
	if err := recoverGeosite(dir); err != nil {
		t.Fatal(err)
	}
	body, err := os.ReadFile(path)
	if err != nil || string(body) != "old" {
		t.Fatalf("previous data not restored: %q %v", body, err)
	}
	if _, err := os.Stat(path + ".rollback"); !os.IsNotExist(err) {
		t.Fatal("recovery marker remains")
	}
}

func TestGeositeRecoveryReturnsWriteFailure(t *testing.T) {
	dir := t.TempDir()
	path := filepath.Join(dir, "geosite.dat")
	if err := os.Mkdir(path, 0700); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(path+".rollback", []byte("old"), 0600); err != nil {
		t.Fatal(err)
	}
	if err := recoverGeosite(dir); err == nil {
		t.Fatal("recovery accepted an unwritable destination")
	}
	if _, err := os.Stat(path + ".rollback"); err != nil {
		t.Fatal("recovery discarded the backup")
	}
}

func TestGeositeRestartFailureRestoresPreviousData(t *testing.T) {
	dir := t.TempDir()
	path := filepath.Join(dir, "geosite.dat")
	if err := os.WriteFile(path, []byte("old"), 0600); err != nil {
		t.Fatal(err)
	}
	starts := 0
	err := switchGeosite(dir, []byte("new"), func(rollback bool) error {
		starts++
		if starts == 1 {
			return fmt.Errorf("candidate failed")
		}
		return nil
	})
	if err == nil || starts != 2 {
		t.Fatalf("restart outcome: %d %v", starts, err)
	}
	body, err := os.ReadFile(path)
	if err != nil || string(body) != "old" {
		t.Fatalf("previous data not restored: %q %v", body, err)
	}
}

func TestGeositeUnchangedDoesNotRestart(t *testing.T) {
	dir := t.TempDir()
	if err := os.WriteFile(filepath.Join(dir, "geosite.dat"), []byte("same"), 0600); err != nil {
		t.Fatal(err)
	}
	if err := switchGeosite(dir, []byte("same"), func(bool) error {
		t.Fatal("unchanged data restarted the core")
		return nil
	}); err != nil {
		t.Fatal(err)
	}
}

func TestGeositeSwitchKeepsNewDataAfterSuccess(t *testing.T) {
	dir := t.TempDir()
	path := filepath.Join(dir, "geosite.dat")
	if err := os.WriteFile(path, []byte("old"), 0600); err != nil {
		t.Fatal(err)
	}
	if err := switchGeosite(dir, []byte("new"), func(rollback bool) error {
		body, err := os.ReadFile(path)
		if rollback || err != nil || string(body) != "new" {
			t.Fatalf("candidate unavailable at restart: %q %v", body, err)
		}
		return nil
	}); err != nil {
		t.Fatal(err)
	}
	if _, err := os.Stat(path + ".rollback"); !os.IsNotExist(err) {
		t.Fatal("recovery marker remains after successful restart")
	}
	previous, err := os.ReadFile(path + ".previous")
	if err != nil || string(previous) != "old" {
		t.Fatalf("previous data unavailable: %q %v", previous, err)
	}
}

func TestDownloadGeositeRejectsChecksumMismatch(t *testing.T) {
	client := &http.Client{Transport: geositeTransport(func(r *http.Request) (*http.Response, error) {
		content := "bad data"
		if r.URL.Path == "/checksum" {
			content = "0000000000000000000000000000000000000000000000000000000000000000  dlc.dat\n"
		}
		return &http.Response{StatusCode: 200, Body: io.NopCloser(strings.NewReader(content))}, nil
	})}
	if _, err := downloadGeosite(context.Background(), client, "https://example.test/data", "https://example.test/checksum"); err == nil {
		t.Fatal("accepted data with incorrect checksum")
	}
}

func TestLatestGeositeUsesOneReleaseForBothFiles(t *testing.T) {
	body := "data"
	digest := sha256.Sum256([]byte(body))
	client := &http.Client{Transport: geositeTransport(func(r *http.Request) (*http.Response, error) {
		content := ""
		switch r.URL.String() {
		case "https://api.github.com/repos/v2fly/domain-list-community/releases/latest":
			content = `{"tag_name":"20261027070632"}`
		case "https://github.com/v2fly/domain-list-community/releases/download/20261027070632/dlc.dat":
			content = body
		case "https://github.com/v2fly/domain-list-community/releases/download/20261027070632/dlc.dat.sha256sum":
			content = fmt.Sprintf("%x  dlc.dat\n", digest)
		default:
			t.Fatalf("unexpected request %s", r.URL)
		}
		return &http.Response{StatusCode: 200, Body: io.NopCloser(strings.NewReader(content))}, nil
	})}
	got, tag, err := latestGeosite(context.Background(), client)
	if err != nil || tag != "20261027070632" || string(got) != body {
		t.Fatalf("unexpected release: %q %q %v", tag, got, err)
	}
}
