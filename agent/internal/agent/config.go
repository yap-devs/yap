package agent

import (
	"encoding/json"
	"errors"
	"net/url"
	"os"
	"path/filepath"
	"strings"
)

type Config struct {
	PanelURL       string `json:"panel_url"`
	Token          string `json:"token"`
	StatePath      string `json:"state_path"`
	CoreBinary     string `json:"core_binary"`
	CoreConfigPath string `json:"core_config_path"`
	CoreSocketPath string `json:"core_socket_path"`
}

func LoadConfig(path string) (Config, error) {
	var c Config
	stat, err := os.Stat(path)
	if err != nil {
		return c, err
	}
	if stat.Mode().Perm()&0077 != 0 {
		return c, errors.New("agent configuration must have mode 0600")
	}
	b, err := os.ReadFile(path)
	if err != nil {
		return c, err
	}
	if err = json.Unmarshal(b, &c); err != nil {
		return c, err
	}
	return c, c.Validate()
}

func (c Config) Validate() error {
	u, err := url.Parse(c.PanelURL)
	if err != nil || u.Scheme != "https" || u.Host == "" || u.User != nil || u.RawQuery != "" || u.Fragment != "" {
		return errors.New("panel_url must be an HTTPS URL without credentials, query or fragment")
	}
	if c.Token == "" {
		return errors.New("node token is required")
	}
	for _, p := range []string{c.StatePath, c.CoreBinary, c.CoreConfigPath} {
		if !filepath.IsAbs(p) {
			return errors.New("state and core paths must be absolute local paths")
		}
	}
	if !validCoreSocketPath(c.CoreSocketPath) {
		return errors.New("core_socket_path must be a clean absolute Unix socket path shorter than 108 bytes")
	}
	for _, path := range []string{c.StatePath, c.StatePath + ".lock", c.CoreBinary, c.CoreConfigPath, c.CoreConfigPath + ".new"} {
		if c.CoreSocketPath == path || c.CoreSocketPath+".lock" == path {
			return errors.New("core_socket_path conflicts with an agent file")
		}
	}
	return nil
}

func validCoreSocketPath(path string) bool {
	return filepath.IsAbs(path) && filepath.Clean(path) == path && len(path) < 108 && !strings.ContainsAny(path, ",\x00")
}

type Desired struct {
	PollIntervalSeconds    int             `json:"poll_interval_seconds"`
	TrafficIntervalSeconds int             `json:"traffic_interval_seconds"`
	Revision               int64           `json:"revision"`
	CoreConfig             json.RawMessage `json:"core_config"`
	Routes                 []Route         `json:"routes"`
	Users                  []User          `json:"users"`
	ValidUntil             int64           `json:"valid_until"`
}

type Route struct {
	ID             int64  `json:"id"`
	InboundTag     string `json:"inbound_tag"`
	ListenPort     int    `json:"listen_port"`
	ForLowPriority bool   `json:"for_low_priority"`
}
type User struct {
	ID          int64  `json:"id"`
	UUID        string `json:"uuid"`
	Email       string `json:"email"`
	LowPriority bool   `json:"low_priority"`
}
type Record struct {
	UserID   int64 `json:"user_id"`
	RouteID  int64 `json:"route_id"`
	Uplink   int64 `json:"uplink"`
	Downlink int64 `json:"downlink"`
}
type Batch struct {
	UUID    string   `json:"batch_uuid"`
	Records []Record `json:"records"`
}
type Counter struct{ UserID, RouteID, Uplink, Downlink int64 }
