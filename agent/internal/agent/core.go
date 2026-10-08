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
	"reflect"
	"sort"
	"strconv"
	"strings"
	"syscall"
	"time"

	command "github.com/v2fly/v2ray-core/v5/app/proxyman/command"
	stats "github.com/v2fly/v2ray-core/v5/app/stats/command"
	"github.com/v2fly/v2ray-core/v5/common/protocol"
	"github.com/v2fly/v2ray-core/v5/common/serial"
	"github.com/v2fly/v2ray-core/v5/proxy/vmess"
	"google.golang.org/grpc"
	"google.golang.org/grpc/codes"
	"google.golang.org/grpc/credentials/insecure"
	"google.golang.org/grpc/status"
)

const Capability = "yap-port-stats-v1"

type Core struct {
	BeforeRestart func(context.Context) error
	config        Config
	process       *exec.Cmd
	done          chan error
	connection    *grpc.ClientConn
	desired       Desired
	dirtyUsers    map[string]map[string]struct{}
	generation    string
}

func NewCore(c Config) *Core { return &Core{config: c} }
func (c *Core) Check(ctx context.Context) error {
	b, e := exec.CommandContext(ctx, c.config.CoreBinary, "version").Output()
	if e != nil {
		return errors.New("cannot read local core version")
	}
	if !strings.Contains(string(b), Capability) || !strings.Contains(string(b), "V2Ray 5.53.0 ") {
		return errors.New("core lacks required " + Capability + " capability")
	}
	return nil
}
func (c *Core) Generation() string { return c.generation }
func (c *Core) Running() bool {
	if c.process == nil {
		return false
	}
	select {
	case <-c.done:
		c.process = nil
		return false
	default:
		return true
	}
}
func (c *Core) Stop() {
	if c.connection != nil {
		c.connection.Close()
		c.connection = nil
	}
	if c.process != nil {
		_ = c.process.Process.Signal(os.Interrupt)
		select {
		case <-c.done:
		case <-time.After(5 * time.Second):
			_ = c.process.Process.Kill()
			<-c.done
		}
		c.process = nil
	}
}
func (c *Core) Apply(ctx context.Context, d Desired) error {
	rendered, e := RenderConfig(d, c.config.CoreSocketPath)
	if e != nil {
		return e
	}
	if c.Running() && reflect.DeepEqual(c.desired.CoreConfig, d.CoreConfig) && reflect.DeepEqual(c.desired.Routes, d.Routes) {
		if e = c.syncUsers(ctx, d); e == nil {
			c.desired = cloneDesired(d)
			return nil
		}
	}
	temp := c.config.CoreConfigPath + ".new"
	if e = os.WriteFile(temp, rendered, 0600); e != nil {
		return e
	}
	defer os.Remove(temp)
	if e = os.Chmod(temp, 0600); e != nil {
		return e
	}
	if e = exec.CommandContext(ctx, c.config.CoreBinary, "test", "-format=json", "-c", temp).Run(); e != nil {
		return errors.New("core rejected candidate configuration")
	}
	if c.Running() && c.BeforeRestart != nil {
		if e = c.BeforeRestart(ctx); e != nil {
			return fmt.Errorf("final pre-restart sample failed: %w", e)
		}
	}
	c.Stop()
	if e = prepareCoreSocket(c.config.CoreSocketPath); e != nil {
		return e
	}
	if e = os.Rename(temp, c.config.CoreConfigPath); e != nil {
		return e
	}
	c.process = exec.Command(c.config.CoreBinary, "run", "-format=json", "-c", c.config.CoreConfigPath)
	c.process.SysProcAttr = &syscall.SysProcAttr{Pdeathsig: syscall.SIGKILL}
	if e = c.process.Start(); e != nil {
		c.process = nil
		return errors.New("core start failed")
	}
	process := c.process
	c.done = make(chan error, 1)
	go func() { c.done <- process.Wait() }()
	c.generation = NewUUID()
	dialctx, cancel := context.WithTimeout(ctx, 10*time.Second)
	defer cancel()
	c.connection, e = grpc.DialContext(dialctx, "unix://"+c.config.CoreSocketPath, grpc.WithTransportCredentials(insecure.NewCredentials()), grpc.WithBlock())
	if e != nil {
		c.Stop()
		return errors.New("local core gRPC readiness failed")
	}
	c.desired = cloneDesired(d)
	c.dirtyUsers = nil
	return nil
}
func (c *Core) syncUsers(ctx context.Context, d Desired) error {
	tags := map[string]bool{}
	for _, r := range d.Routes {
		tags[r.InboundTag] = r.ForLowPriority
	}
	if c.dirtyUsers == nil {
		c.dirtyUsers = map[string]map[string]struct{}{}
	}
	client := command.NewHandlerServiceClient(c.connection)
	for tag, allowsLow := range tags {
		previous := handlerUsers(c.desired.Users, allowsLow)
		next := handlerUsers(d.Users, allowsLow)
		changed := c.dirtyUsers[tag]
		if changed == nil {
			changed = map[string]struct{}{}
			c.dirtyUsers[tag] = changed
		}
		for email, u := range previous {
			if n, ok := next[email]; !ok || n != u {
				changed[email] = struct{}{}
			}
		}
		for email, u := range next {
			if old, ok := previous[email]; !ok || old != u {
				changed[email] = struct{}{}
			}
		}
		// Keep every attempted identity until the whole snapshot succeeds.
		// RPC failures can leave earlier operations applied in the handler.
		for email := range changed {
			if e := removeInboundUser(ctx, client, tag, email); e != nil {
				return errors.New("core user removal failed")
			}
		}
		// Remove old UUIDs before installing any reassigned identities.
		for email := range changed {
			if u, ok := next[email]; ok {
				level := uint32(0)
				if u.LowPriority {
					level = 1
				}
				_, e := client.AlterInbound(ctx, &command.AlterInboundRequest{Tag: tag, Operation: serial.ToTypedMessage(&command.AddUserOperation{User: &protocol.User{Email: u.Email, Level: level, Account: serial.ToTypedMessage(&vmess.Account{Id: u.UUID})}})})
				if e != nil {
					return errors.New("core user addition failed")
				}
			}
		}
	}
	c.dirtyUsers = nil
	return nil
}

func handlerUsers(users []User, allowsLow bool) map[string]User {
	result := map[string]User{}
	for _, u := range users {
		if !u.LowPriority || allowsLow {
			result[u.Email] = u
		}
	}
	return result
}

func removeInboundUser(ctx context.Context, client command.HandlerServiceClient, tag, email string) error {
	_, err := client.AlterInbound(ctx, &command.AlterInboundRequest{Tag: tag, Operation: serial.ToTypedMessage(&command.RemoveUserOperation{Email: email})})
	// Only the pinned VMess handler's exact missing-user result is idempotent.
	if status.Code(err) == codes.Unknown && strings.HasSuffix(status.Convert(err).Message(), "User "+email+" not found.") {
		return nil
	}
	return err
}

func (c *Core) Counters(ctx context.Context) ([]Counter, error) {
	if !c.Running() || c.connection == nil {
		return nil, errors.New("core is not running")
	}
	client := stats.NewStatsServiceClient(c.connection)
	var result []Counter
	for _, route := range c.desired.Routes {
		records, err := queryPortStats(ctx, client, route.ListenPort, "", false)
		if err != nil {
			return nil, fmt.Errorf("core stats query failed: %w", err)
		}
		values := map[string]int64{}
		for _, record := range records {
			values[record.Name] = record.Value
		}
		counters, err := ParseCounters(values, []Route{route})
		if err != nil {
			return nil, err
		}
		result = append(result, counters...)
	}
	sort.Slice(result, func(i, j int) bool {
		if result[i].UserID != result[j].UserID {
			return result[i].UserID < result[j].UserID
		}
		return result[i].RouteID < result[j].RouteID
	})
	return result, nil
}

func queryPortStats(ctx context.Context, client stats.StatsServiceClient, port int, prefix string, exact bool) ([]*stats.Stat, error) {
	identity := prefix
	if prefix == "" {
		identity = "[1-9][0-9]*"
	} else if !exact {
		identity += "[0-9]*"
	}
	pattern := "^user>>>user-" + identity + "-port-" + strconv.Itoa(port) + ">>>traffic>>>"
	response, err := client.QueryStats(ctx, &stats.QueryStatsRequest{Pattern: pattern, Regexp: true, Reset_: false}, grpc.MaxCallRecvMsgSize(4<<20))
	if err == nil {
		return response.Stat, nil
	}
	if status.Code(err) != codes.ResourceExhausted || exact || len(prefix) >= 19 {
		return nil, err
	}
	// Exact IDs and decimal descendants are disjoint; cumulative reads never reset.
	var records []*stats.Stat
	firstDigit := 1
	if prefix != "" {
		exactRecords, err := queryPortStats(ctx, client, port, prefix, true)
		if err != nil {
			return nil, err
		}
		records = append(records, exactRecords...)
		firstDigit = 0
	}
	for digit := firstDigit; digit <= 9; digit++ {
		part, err := queryPortStats(ctx, client, port, prefix+strconv.Itoa(digit), false)
		if err != nil {
			return nil, err
		}
		records = append(records, part...)
	}
	return records, nil
}

func ParseCounters(values map[string]int64, routes []Route) ([]Counter, error) {
	ports := map[int]int64{}
	for _, r := range routes {
		ports[r.ListenPort] = r.ID
	}
	result := map[string]*Counter{}
	for name, value := range values {
		parts := strings.Split(name, ">>>")
		if len(parts) != 4 || parts[0] != "user" || parts[2] != "traffic" {
			continue
		}
		identity := strings.Split(parts[1], "-port-")
		if len(identity) != 2 || !strings.HasPrefix(identity[0], "user-") {
			return nil, errors.New("core returned unattributed user statistics")
		}
		user, e := strconv.ParseInt(strings.TrimPrefix(identity[0], "user-"), 10, 64)
		if e != nil || user < 1 {
			return nil, errors.New("invalid stats user")
		}
		port, e := strconv.Atoi(identity[1])
		if e != nil {
			return nil, e
		}
		route, ok := ports[port]
		if !ok {
			return nil, errors.New("stats refer to an unknown route port")
		}
		if value < 0 {
			return nil, errors.New("negative stats")
		}
		key := fmt.Sprintf("%d/%d", user, route)
		counter := result[key]
		if counter == nil {
			counter = &Counter{UserID: user, RouteID: route}
			result[key] = counter
		}
		switch parts[3] {
		case "uplink":
			counter.Uplink = value
		case "downlink":
			counter.Downlink = value
		default:
			return nil, errors.New("unknown stats direction")
		}
	}
	out := make([]Counter, 0, len(result))
	for _, c := range result {
		out = append(out, *c)
	}
	sort.Slice(out, func(i, j int) bool {
		if out[i].UserID == out[j].UserID {
			return out[i].RouteID < out[j].RouteID
		}
		return out[i].UserID < out[j].UserID
	})
	return out, nil
}

func prepareCoreSocket(path string) error {
	parent := filepath.Dir(path)
	if err := os.MkdirAll(parent, 0700); err != nil {
		return err
	}
	directory, err := os.Stat(parent)
	if err != nil {
		return err
	}
	if !directory.IsDir() || directory.Mode().Perm()&0077 != 0 {
		return errors.New("core socket directory must be private (mode 0700)")
	}
	entry, err := os.Lstat(path)
	if errors.Is(err, os.ErrNotExist) {
		return nil
	}
	if err != nil {
		return err
	}
	if entry.Mode()&os.ModeSocket == 0 {
		return errors.New("core socket path contains a non-socket file")
	}
	connection, err := net.DialTimeout("unix", path, 100*time.Millisecond)
	if err == nil {
		connection.Close()
		return errors.New("core socket is already in use")
	}
	if errors.Is(err, os.ErrNotExist) {
		return nil
	}
	if !errors.Is(err, syscall.ECONNREFUSED) {
		return errors.New("cannot verify stale core socket")
	}
	return os.Remove(path)
}

func RenderConfig(d Desired, socketPath string) ([]byte, error) {
	if d.Revision < 1 || d.ValidUntil <= 0 {
		return nil, errors.New("desired configuration has expired or invalid revision")
	}
	var config map[string]any
	if e := json.Unmarshal(d.CoreConfig, &config); e != nil {
		return nil, e
	}
	if config == nil {
		return nil, errors.New("missing core configuration")
	}
	grouped := map[string][]string{}
	lowFlags := map[string]bool{}
	ports := map[int]bool{}
	routeIDs := map[int64]bool{}
	for _, r := range d.Routes {
		if r.ID < 1 || !strings.HasPrefix(r.InboundTag, "yap-") || r.InboundTag == "yap-api" || r.ListenPort < 1 || r.ListenPort > 65535 || ports[r.ListenPort] || routeIDs[r.ID] {
			return nil, errors.New("invalid or duplicate route")
		}
		if prior, ok := lowFlags[r.InboundTag]; ok && prior != r.ForLowPriority {
			return nil, errors.New("shared handler routes require identical authorization")
		}
		lowFlags[r.InboundTag] = r.ForLowPriority
		ports[r.ListenPort] = true
		routeIDs[r.ID] = true
		grouped[r.InboundTag] = append(grouped[r.InboundTag], strconv.Itoa(r.ListenPort))
	}
	clients := []any{}
	users := map[int64]bool{}
	uuids := map[string]bool{}
	for _, u := range d.Users {
		uuidKey := strings.ToLower(u.UUID)
		if u.ID < 1 || users[u.ID] || uuids[uuidKey] || u.Email != "user-"+strconv.FormatInt(u.ID, 10) || !validUUID(u.UUID) {
			return nil, errors.New("invalid or duplicate user")
		}
		users[u.ID] = true
		uuids[uuidKey] = true
		level := 0
		if u.LowPriority {
			level = 1
		}
		clients = append(clients, map[string]any{"id": u.UUID, "email": u.Email, "level": level, "alterId": 0})
	}
	raw, ok := config["inbounds"].([]any)
	if !ok {
		return nil, errors.New("missing inbound skeleton")
	}
	inbounds := []any{}
	seen := map[string]bool{}
	for _, v := range raw {
		inbound, ok := v.(map[string]any)
		if !ok {
			return nil, errors.New("invalid inbound")
		}
		tag, _ := inbound["tag"].(string)
		routePorts, ok := grouped[tag]
		if !ok {
			continue
		}
		if seen[tag] || inbound["protocol"] != "vmess" {
			return nil, errors.New("routes require unique VMess inbound skeletons")
		}
		if rawStream, exists := inbound["streamSettings"]; exists {
			stream, ok := rawStream.(map[string]any)
			if !ok {
				return nil, errors.New("invalid managed stream settings")
			}
			if network, exists := stream["network"]; exists && network != "tcp" && network != "" {
				return nil, errors.New("managed VMess supports plain TCP only")
			}
			if security, exists := stream["security"]; exists && security != "none" && security != "" {
				return nil, errors.New("managed VMess does not support TLS")
			}
		}
		seen[tag] = true
		sort.Slice(routePorts, func(i, j int) bool {
			a, _ := strconv.Atoi(routePorts[i])
			b, _ := strconv.Atoi(routePorts[j])
			return a < b
		})
		first, _ := strconv.Atoi(routePorts[0])
		for i, port := range routePorts {
			n, _ := strconv.Atoi(port)
			if n != first+i {
				return nil, errors.New("shared handler ports must be contiguous")
			}
		}
		inbound["port"] = routePorts[0]
		if len(routePorts) > 1 {
			inbound["port"] = routePorts[0] + "-" + routePorts[len(routePorts)-1]
		}
		settings, _ := inbound["settings"].(map[string]any)
		if settings == nil {
			settings = map[string]any{}
		}
		eligible := []any{}
		for _, client := range clients {
			entry := client.(map[string]any)
			if entry["level"] == 1 && !lowFlags[tag] {
				continue
			}
			eligible = append(eligible, client)
		}
		settings["clients"] = eligible
		inbound["settings"] = settings
		inbounds = append(inbounds, inbound)
	}
	if len(seen) != len(grouped) {
		return nil, errors.New("route references missing inbound skeleton")
	}
	if !validCoreSocketPath(socketPath) {
		return nil, errors.New("invalid core Unix socket path")
	}
	inbounds = append(inbounds, map[string]any{"tag": "yap-api", "listen": socketPath + ",0600", "protocol": "dokodemo-door", "settings": map[string]any{"address": "127.0.0.1", "network": "unix"}})
	config["inbounds"] = inbounds
	config["api"] = map[string]any{"tag": "yap-api", "services": []string{"HandlerService", "StatsService"}}
	config["stats"] = map[string]any{}
	policy, _ := config["policy"].(map[string]any)
	if policy == nil {
		policy = map[string]any{}
	}
	levels, _ := policy["levels"].(map[string]any)
	if levels == nil {
		levels = map[string]any{}
	}
	for _, level := range []string{"0", "1"} {
		p, _ := levels[level].(map[string]any)
		if p == nil {
			p = map[string]any{}
		}
		p["statsUserUplink"] = true
		p["statsUserDownlink"] = true
		levels[level] = p
	}
	policy["levels"] = levels
	config["policy"] = policy
	routing, _ := config["routing"].(map[string]any)
	if routing == nil {
		routing = map[string]any{}
	}
	rules, _ := routing["rules"].([]any)
	routing["rules"] = append([]any{map[string]any{"type": "field", "inboundTag": []string{"yap-api"}, "outboundTag": "yap-api"}}, rules...)
	config["routing"] = routing
	return json.Marshal(config)
}
func validUUID(s string) bool {
	if len(s) != 36 {
		return false
	}
	for i, c := range s {
		if i == 8 || i == 13 || i == 18 || i == 23 {
			if c != '-' {
				return false
			}
		} else if !(c >= '0' && c <= '9' || c >= 'a' && c <= 'f' || c >= 'A' && c <= 'F') {
			return false
		}
	}
	return true
}

func cloneDesired(d Desired) Desired {
	d.CoreConfig = append(json.RawMessage(nil), d.CoreConfig...)
	d.Routes = append([]Route(nil), d.Routes...)
	d.Users = append([]User(nil), d.Users...)
	return d
}
