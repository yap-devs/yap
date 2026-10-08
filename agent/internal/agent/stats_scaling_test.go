package agent

import (
	"context"
	"fmt"
	"net"
	"os/exec"
	"regexp"
	"strings"
	"sync/atomic"
	"testing"
	"time"

	stats "github.com/v2fly/v2ray-core/v5/app/stats/command"
	"google.golang.org/grpc"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/credentials/insecure"
	"google.golang.org/grpc/status"
	"google.golang.org/protobuf/proto"
)

type scalingStatsServer struct {
	stats.UnimplementedStatsServiceServer
	values []*stats.Stat
	calls  atomic.Int32
	split  map[string]bool
}

func (s *scalingStatsServer) QueryStats(ctx context.Context, request *stats.QueryStatsRequest) (*stats.QueryStatsResponse, error) {
	s.calls.Add(1)
	if request.Reset_ {
		return nil, fmt.Errorf("cumulative counters must not be reset")
	}
	if s.split[request.Pattern] {
		return nil, status.Error(codes.ResourceExhausted, "force another partition level")
	}
	var pattern *regexp.Regexp
	if request.Regexp {
		var err error
		pattern, err = regexp.Compile(request.Pattern)
		if err != nil {
			return nil, err
		}
	}
	response := &stats.QueryStatsResponse{}
	for _, value := range s.values {
		if err := ctx.Err(); err != nil {
			return nil, err
		}
		if (pattern != nil && pattern.MatchString(value.Name)) || (pattern == nil && strings.Contains(value.Name, request.Pattern)) {
			response.Stat = append(response.Stat, value)
		}
	}
	return response, nil
}

func TestCountersPartitionResponsesAboveGRPCDefaultLimit(t *testing.T) {
	for _, tc := range []struct{ users, ports int }{{10000, 4}, {50000, 1}} {
		t.Run(fmt.Sprintf("%d-users-%d-ports", tc.users, tc.ports), func(t *testing.T) {
			service := &scalingStatsServer{}
			var routes []Route
			for p := 0; p < tc.ports; p++ {
				routes = append(routes, Route{ID: int64(p + 1), ListenPort: 20001 + p})
				for u := 1; u <= tc.users; u++ {
					for _, direction := range []string{"uplink", "downlink"} {
						service.values = append(service.values, &stats.Stat{Name: fmt.Sprintf("user>>>user-%d-port-%d>>>traffic>>>%s", u, 20001+p, direction), Value: 1 << 30})
					}
				}
			}
			if proto.Size(&stats.QueryStatsResponse{Stat: service.values}) <= 4<<20 {
				t.Fatal("fixture does not exceed the default receive limit")
			}
			core := newScalingCore(t, service, routes)
			ctx, cancel := context.WithTimeout(context.Background(), 60*time.Second)
			defer cancel()
			counters, err := core.Counters(ctx)
			if err != nil {
				t.Fatal(err)
			}
			if len(counters) != tc.users*tc.ports || service.calls.Load() < 2 {
				t.Fatalf("incomplete partitioned sample: counters=%d calls=%d", len(counters), service.calls.Load())
			}
			seen := map[string]bool{}
			for _, counter := range counters {
				key := fmt.Sprintf("%d/%d", counter.UserID, counter.RouteID)
				if seen[key] || counter.Uplink != 1<<30 || counter.Downlink != 1<<30 {
					t.Fatalf("duplicate or incorrect counter: %+v", counter)
				}
				seen[key] = true
			}
		})
	}
}

func newScalingCore(t *testing.T, service *scalingStatsServer, routes []Route) *Core {
	t.Helper()
	listener, err := net.Listen("tcp", "127.0.0.1:0")
	if err != nil {
		t.Fatal(err)
	}
	server := grpc.NewServer()
	stats.RegisterStatsServiceServer(server, service)
	go server.Serve(listener)
	t.Cleanup(server.Stop)
	connection, err := grpc.NewClient(listener.Addr().String(), grpc.WithTransportCredentials(insecure.NewCredentials()))
	if err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { connection.Close() })
	core := NewCore(Config{})
	core.connection = connection
	core.process = &exec.Cmd{}
	core.done = make(chan error)
	core.desired.Routes = routes
	return core
}

func TestNestedCounterPartitionsKeepExactIDsAndDescendants(t *testing.T) {
	service := &scalingStatsServer{split: map[string]bool{
		"^user>>>user-[1-9][0-9]*-port-20001>>>traffic>>>": true,
		"^user>>>user-1[0-9]*-port-20001>>>traffic>>>":     true,
	}}
	ids := []int{1, 2, 10, 11, 19, 100, 101}
	for _, id := range ids {
		service.values = append(service.values, &stats.Stat{Name: fmt.Sprintf("user>>>user-%d-port-20001>>>traffic>>>uplink", id), Value: int64(id)})
	}
	core := newScalingCore(t, service, []Route{{ID: 1, ListenPort: 20001}})
	ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()
	counters, err := core.Counters(ctx)
	if err != nil {
		t.Fatal(err)
	}
	if len(counters) != len(ids) {
		t.Fatalf("lost or duplicated prefix boundary IDs: %+v", counters)
	}
	for i, id := range ids {
		if counters[i].UserID != int64(id) || counters[i].Uplink != int64(id) || counters[i].RouteID != 1 {
			t.Fatalf("unexpected counter: %+v", counters[i])
		}
	}
}
