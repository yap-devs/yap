package agent

import (
	"bytes"
	"context"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"net/http"
	"strconv"
	"strings"
	"time"
)

const AgentVersion = "0.1.0"
const CoreVersion = "v5.53.0-yap-port-stats-v1"

var ErrPanelUnauthorized = errors.New("panel revoked node authorization")

type Panel struct {
	base, token string
	client      *http.Client
}

func NewPanel(c Config) *Panel {
	return &Panel{strings.TrimRight(c.PanelURL, "/"), c.Token, &http.Client{Timeout: 15 * time.Second, CheckRedirect: func(*http.Request, []*http.Request) error { return http.ErrUseLastResponse }}}
}
func (p *Panel) request(ctx context.Context, method, path string, body []byte) (*http.Response, error) {
	r, e := http.NewRequestWithContext(ctx, method, p.base+path, bytes.NewReader(body))
	if e != nil {
		return nil, e
	}
	r.Header.Set("Authorization", "Bearer "+p.token)
	r.Header.Set("Accept", "application/json")
	if body != nil {
		r.Header.Set("Content-Type", "application/json")
	}
	return p.client.Do(r)
}
func (p *Panel) Fetch(ctx context.Context, revision int64) (*Desired, error) {
	r, e := p.request(ctx, http.MethodGet, "/api/agent/v1/config?revision="+strconv.FormatInt(revision, 10)+"&applied_revision="+strconv.FormatInt(revision, 10)+"&agent_version="+AgentVersion+"&core_version="+CoreVersion, nil)
	if e != nil {
		return nil, errors.New("panel config request failed")
	}
	defer r.Body.Close()
	if r.StatusCode == 304 {
		return nil, nil
	}
	if r.StatusCode == http.StatusUnauthorized {
		return nil, ErrPanelUnauthorized
	}
	if r.StatusCode != 200 {
		return nil, fmt.Errorf("panel config status %d", r.StatusCode)
	}
	var d Desired
	e = json.NewDecoder(io.LimitReader(r.Body, 32<<20)).Decode(&d)
	return &d, e
}
func (p *Panel) Send(ctx context.Context, body []byte) (string, error) {
	r, e := p.request(ctx, http.MethodPost, "/api/agent/v1/traffic", body)
	if e != nil {
		return "", errors.New("panel traffic request failed")
	}
	defer r.Body.Close()
	if r.StatusCode != 200 {
		return "", fmt.Errorf("panel traffic status %d", r.StatusCode)
	}
	var ack struct {
		UUID     string `json:"batch_uuid"`
		Accepted bool   `json:"accepted"`
	}
	if e = json.NewDecoder(io.LimitReader(r.Body, 1<<20)).Decode(&ack); e != nil {
		return "", e
	}
	if !ack.Accepted || ack.UUID == "" {
		return "", errors.New("panel did not acknowledge traffic")
	}
	return ack.UUID, nil
}
