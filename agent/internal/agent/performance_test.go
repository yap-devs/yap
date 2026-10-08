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
	"os/exec"
	"path/filepath"
	"sort"
	"strconv"
	"strings"
	"sync"
	"testing"
	"time"

	stats "github.com/v2fly/v2ray-core/v5/app/stats/command"
)

type performanceResult struct {
	Type                 string    `json:"type"`
	Binary               string    `json:"binary"`
	Version              string    `json:"version"`
	ClientBinary         string    `json:"client_binary"`
	Users                int       `json:"users"`
	Iteration            int       `json:"iteration,omitempty"`
	Iterations           int       `json:"iterations,omitempty"`
	Ports                int       `json:"shared_ports"`
	Requests             int       `json:"requests"`
	Concurrency          int       `json:"concurrency"`
	PayloadBytes         int       `json:"payload_bytes_per_request"`
	ValidationSeconds    float64   `json:"validation_seconds"`
	StartupSeconds       float64   `json:"validation_and_startup_seconds"`
	StartupCPUSeconds    float64   `json:"core_cpu_seconds_at_ready"`
	IdleRSSKiB           float64   `json:"idle_rss_kib"`
	PeakRSSKiB           float64   `json:"peak_rss_kib"`
	LoadedRSSKiB         float64   `json:"loaded_rss_kib"`
	LoadCPUSeconds       float64   `json:"core_load_cpu_seconds"`
	LoadSeconds          float64   `json:"load_seconds"`
	ThroughputMiBSeconds float64   `json:"payload_mib_per_second"`
	CounterUplink        int64     `json:"counter_uplink_bytes,omitempty"`
	CounterDownlink      int64     `json:"counter_downlink_bytes,omitempty"`
	RouteCounters        []Counter `json:"route_counters,omitempty"`
	CounterMode          string    `json:"counter_mode"`
}

// Opt-in measurements use real binaries and traffic. Run without -race for comparable timings.
func TestCorePerformance(t *testing.T) {
	binary := os.Getenv("YAP_TEST_CORE_BINARY")
	if binary == "" || os.Getenv("YAP_TEST_PERFORMANCE") != "1" {
		t.Skip("set YAP_TEST_PERFORMANCE=1 and YAP_TEST_CORE_BINARY for real performance measurements")
	}
	iterations := 3
	if value := os.Getenv("YAP_TEST_PERFORMANCE_ITERATIONS"); value != "" {
		var e error
		iterations, e = strconv.Atoi(value)
		if e != nil || iterations < 3 || iterations > 20 {
			t.Fatal("YAP_TEST_PERFORMANCE_ITERATIONS must be between 3 and 20")
		}
	}
	versionBytes, e := exec.Command(binary, "version").Output()
	if e != nil {
		t.Fatal(e)
	}
	version := strings.TrimSpace(strings.SplitN(string(versionBytes), "\n", 2)[0])
	ticksBytes, e := exec.Command("getconf", "CLK_TCK").Output()
	if e != nil {
		t.Fatal(e)
	}
	ticks, e := strconv.ParseFloat(strings.TrimSpace(string(ticksBytes)), 64)
	if e != nil || ticks <= 0 {
		t.Fatal("cannot determine process CPU clock ticks")
	}
	for _, users := range []int{1000, 10000} {
		t.Run(strconv.Itoa(users), func(t *testing.T) {
			results := make([]performanceResult, 0, iterations)
			for iteration := 1; iteration <= iterations; iteration++ {
				t.Run(fmt.Sprintf("iteration-%d", iteration), func(t *testing.T) {
					result := performanceIteration(t, binary, version, users, iteration, ticks)
					results = append(results, result)
					performanceJSON(t, result)
				})
			}
			if len(results) != iterations {
				t.Fatal("not all performance iterations completed")
			}
			summary := results[0]
			summary.Type = "median"
			summary.Iteration = 0
			summary.Iterations = iterations
			summary.RouteCounters = nil
			summary.CounterUplink = 0
			summary.CounterDownlink = 0
			fields := []func(*performanceResult) *float64{func(r *performanceResult) *float64 { return &r.ValidationSeconds }, func(r *performanceResult) *float64 { return &r.StartupSeconds }, func(r *performanceResult) *float64 { return &r.StartupCPUSeconds }, func(r *performanceResult) *float64 { return &r.IdleRSSKiB }, func(r *performanceResult) *float64 { return &r.PeakRSSKiB }, func(r *performanceResult) *float64 { return &r.LoadedRSSKiB }, func(r *performanceResult) *float64 { return &r.LoadCPUSeconds }, func(r *performanceResult) *float64 { return &r.LoadSeconds }, func(r *performanceResult) *float64 { return &r.ThroughputMiBSeconds }}
			for _, field := range fields {
				values := make([]float64, len(results))
				for i := range results {
					values[i] = *field(&results[i])
				}
				sort.Float64s(values)
				median := values[len(values)/2]
				if len(values)%2 == 0 {
					median = (median + values[len(values)/2-1]) / 2
				}
				*field(&summary) = median
			}
			performanceJSON(t, summary)
		})
	}
}

func performanceIteration(t *testing.T, binary, version string, users, iteration int, ticks float64) performanceResult {
	t.Helper()
	const requests = 128
	const payloadBytes = 4 << 20
	const concurrency = 4
	result := performanceResult{Type: "iteration", Binary: binary, Version: version, Users: users, Iteration: iteration, Ports: 2, Requests: requests, Concurrency: concurrency, PayloadBytes: payloadBytes, CounterMode: "native_user_aggregate"}
	d := fixtureDesired()
	d.Users = nil
	for i := 1; i <= users; i++ {
		d.Users = append(d.Users, User{ID: int64(i), UUID: fmt.Sprintf("00000000-0000-4000-8000-%012x", i), Email: fmt.Sprintf("user-%d", i)})
	}
	apiAddress := testCoreSocketPath(t)
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
	dir := t.TempDir()
	core := NewCore(Config{CoreBinary: binary, CoreConfigPath: filepath.Join(dir, "core.json"), CoreSocketPath: apiAddress})
	defer core.Stop()
	ctx, cancel := context.WithTimeout(context.Background(), 3*time.Minute)
	defer cancel()
	rendered, e := RenderConfig(d, core.config.CoreSocketPath)
	if e != nil {
		t.Fatal(e)
	}
	candidate := filepath.Join(dir, "candidate.json")
	if e = os.WriteFile(candidate, rendered, 0600); e != nil {
		t.Fatal(e)
	}
	started := time.Now()
	if e = exec.CommandContext(ctx, binary, "test", "-format=json", "-c", candidate).Run(); e != nil {
		t.Fatal("candidate validation failed:", e)
	}
	result.ValidationSeconds = time.Since(started).Seconds()
	// Deliberately omit Core.Check so stock release binaries can be measured with the same workload.
	started = time.Now()
	if e = core.Apply(ctx, d); e != nil {
		t.Fatal(e)
	}
	result.StartupSeconds = time.Since(started).Seconds()
	time.Sleep(time.Second)
	pid := core.process.Process.Pid
	idleRSS, _, cpuReady := performanceProcess(t, pid, ticks)
	result.IdleRSSKiB = idleRSS
	result.StartupCPUSeconds = cpuReady
	payload := make([]byte, payloadBytes)
	for i := range payload {
		payload[i] = 'x'
	}
	origin := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Length", strconv.Itoa(len(payload)))
		_, _ = w.Write(payload)
	}))
	defer origin.Close()
	clientBinary := os.Getenv("YAP_TEST_CLIENT_BINARY")
	if clientBinary == "" {
		clientBinary = binary
	}
	result.ClientBinary = clientBinary
	clients := []*http.Client{networkVMessClient(t, clientBinary, d.Routes[0].ListenPort, d.Users[0].UUID), networkVMessClient(t, clientBinary, d.Routes[1].ListenPort, d.Users[0].UUID)}
	for _, client := range clients {
		client.Timeout = 30 * time.Second
	}
	_, _, cpuBefore := performanceProcess(t, pid, ticks)
	jobs := make(chan int, requests)
	for i := 0; i < requests; i++ {
		jobs <- i
	}
	close(jobs)
	failures := make(chan error, requests)
	var workers sync.WaitGroup
	started = time.Now()
	for i := 0; i < concurrency; i++ {
		workers.Add(1)
		go func() {
			defer workers.Done()
			for request := range jobs {
				response, e := clients[request%2].Get(origin.URL)
				if e != nil {
					failures <- e
					continue
				}
				count, e := io.Copy(io.Discard, response.Body)
				response.Body.Close()
				if e != nil {
					failures <- e
				} else if response.StatusCode != 200 || count != payloadBytes {
					failures <- fmt.Errorf("payload status=%d bytes=%d", response.StatusCode, count)
				}
			}
		}()
	}
	workers.Wait()
	result.LoadSeconds = time.Since(started).Seconds()
	close(failures)
	for failure := range failures {
		t.Error(failure)
	}
	if t.Failed() {
		t.FailNow()
	}
	result.ThroughputMiBSeconds = float64(requests*payloadBytes) / (1 << 20) / result.LoadSeconds
	rss, peak, cpuAfter := performanceProcess(t, pid, ticks)
	result.LoadedRSSKiB = rss
	result.PeakRSSKiB = peak
	result.LoadCPUSeconds = cpuAfter - cpuBefore
	response, e := stats.NewStatsServiceClient(core.connection).QueryStats(ctx, &stats.QueryStatsRequest{Pattern: "user>>>", Reset_: false})
	if e != nil {
		t.Fatal(e)
	}
	values := map[string]int64{}
	for _, stat := range response.Stat {
		values[stat.Name] = stat.Value
		if strings.HasSuffix(stat.Name, ">>>uplink") {
			result.CounterUplink += stat.Value
		}
		if strings.HasSuffix(stat.Name, ">>>downlink") {
			result.CounterDownlink += stat.Value
		}
	}
	if result.CounterDownlink < int64(requests*payloadBytes) {
		t.Fatalf("core counters below delivered payload: %d", result.CounterDownlink)
	}
	if strings.Contains(version, Capability) {
		result.CounterMode = "patched_user_port"
		result.RouteCounters, e = ParseCounters(values, d.Routes)
		if e != nil {
			t.Fatal(e)
		}
		routes := map[int64]int64{}
		for _, counter := range result.RouteCounters {
			routes[counter.RouteID] += counter.Downlink
		}
		for _, route := range d.Routes {
			if routes[route.ID] < requests/2*payloadBytes {
				t.Fatalf("route %d below balanced payload: %d", route.ID, routes[route.ID])
			}
		}
	}
	return result
}

func performanceProcess(t *testing.T, pid int, ticks float64) (rss, peak, cpu float64) {
	t.Helper()
	status, e := os.ReadFile(fmt.Sprintf("/proc/%d/status", pid))
	if e != nil {
		t.Fatal(e)
	}
	for _, line := range strings.Split(string(status), "\n") {
		fields := strings.Fields(line)
		if len(fields) < 2 {
			continue
		}
		switch fields[0] {
		case "VmRSS:":
			rss, _ = strconv.ParseFloat(fields[1], 64)
		case "VmHWM:":
			peak, _ = strconv.ParseFloat(fields[1], 64)
		}
	}
	stat, e := os.ReadFile(fmt.Sprintf("/proc/%d/stat", pid))
	if e != nil {
		t.Fatal(e)
	}
	end := strings.LastIndexByte(string(stat), ')')
	if end < 0 {
		t.Fatal("invalid process stat")
	}
	fields := strings.Fields(string(stat[end+1:]))
	if len(fields) < 13 {
		t.Fatal("incomplete process stat")
	}
	user, e := strconv.ParseFloat(fields[11], 64)
	if e != nil {
		t.Fatal(e)
	}
	system, e := strconv.ParseFloat(fields[12], 64)
	if e != nil {
		t.Fatal(e)
	}
	return rss, peak, (user + system) / ticks
}
func performanceJSON(t *testing.T, result performanceResult) {
	t.Helper()
	line, e := json.Marshal(result)
	if e != nil {
		t.Fatal(e)
	}
	fmt.Println(string(line))
}
