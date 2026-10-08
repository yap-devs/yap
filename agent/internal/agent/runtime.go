package agent

import (
	"context"
	"errors"
	"log"
	"math/rand"
	"sync"
	"time"
)

type Runtime struct {
	store   *Store
	panel   *Panel
	core    *Core
	mu      sync.Mutex
	desired Desired
}

func NewRuntime(s *Store, p *Panel, c *Core) *Runtime {
	r := &Runtime{store: s, panel: p, core: c}
	c.BeforeRestart = func(ctx context.Context) error {
		samplectx, cancel := context.WithTimeout(ctx, 5*time.Second)
		defer cancel()
		return r.sample(samplectx)
	}
	return r
}
func (r *Runtime) Run(ctx context.Context) error {
	checkctx, cancel := context.WithTimeout(ctx, 10*time.Second)
	e := r.core.Check(checkctx)
	cancel()
	if e != nil {
		return e
	}
	d, e := r.store.Desired()
	if e != nil {
		return e
	}
	if d.Revision > 0 {
		applyctx, cancel := context.WithTimeout(ctx, 20*time.Second)
		e = r.core.Apply(applyctx, d)
		cancel()
		if e != nil {
			return e
		}
		r.desired = d
	}
	var wg sync.WaitGroup
	wg.Add(3)
	go func() { defer wg.Done(); r.pollLoop(ctx) }()
	go func() { defer wg.Done(); r.trafficLoop(ctx) }()
	go func() { defer wg.Done(); r.sampleLoop(ctx) }()
	<-ctx.Done()
	wg.Wait()
	r.mu.Lock()
	defer r.mu.Unlock()
	finalctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
	defer cancel()
	if r.core.Running() {
		if e = r.sample(finalctx); e != nil {
			log.Print("final traffic sample failed")
		}
	}
	r.core.Stop()
	return nil
}
func (r *Runtime) pollLoop(ctx context.Context) {
	for {
		if e := r.poll(ctx); e != nil {
			log.Print("configuration reconciliation failed: ", e)
		}
		r.mu.Lock()
		poll, _ := snapshotIntervals(r.desired)
		r.mu.Unlock()
		timer := time.NewTimer(poll - poll/10 + time.Duration(rand.Int63n(int64(poll/5)+1)))
		select {
		case <-ctx.Done():
			timer.Stop()
			return
		case <-timer.C:
		}
	}
}
func (r *Runtime) poll(ctx context.Context) error {
	r.mu.Lock()
	revision := r.desired.Revision
	if revision > 0 && !r.core.Running() {
		applyctx, cancel := context.WithTimeout(ctx, 20*time.Second)
		e := r.core.Apply(applyctx, r.desired)
		cancel()
		if e != nil {
			r.mu.Unlock()
			return e
		}
	}
	r.mu.Unlock()
	d, e := r.panel.Fetch(ctx, revision)
	if e != nil {
		if errors.Is(e, ErrPanelUnauthorized) {
			r.mu.Lock()
			defer r.mu.Unlock()
			if r.core.Running() {
				samplectx, cancel := context.WithTimeout(ctx, 5*time.Second)
				if sampleErr := r.sample(samplectx); sampleErr != nil {
					log.Print("final traffic sample before authorization revocation failed: ", sampleErr)
				}
				cancel()
			}
			r.core.Stop()
			r.desired = Desired{}
			if saveErr := r.store.SaveDesired(r.desired); saveErr != nil {
				return saveErr
			}
		}
		return e
	}
	r.mu.Lock()
	defer r.mu.Unlock()
	if d == nil {
		if r.desired.Revision > 0 && !r.core.Running() {
			applyctx, cancel := context.WithTimeout(ctx, 20*time.Second)
			defer cancel()
			return r.core.Apply(applyctx, r.desired)
		}
		return nil
	}
	if d.ValidUntil <= time.Now().Unix() {
		return errors.New("panel sent expired snapshot")
	}
	if r.core.Running() {
		samplectx, cancel := context.WithTimeout(ctx, 5*time.Second)
		e = r.sample(samplectx)
		cancel()
		if e != nil {
			log.Print("traffic sample deferred during authorization reconciliation: ", e)
			// Save new credentials on the existing topology before any guarded restart.
			authorization := cloneDesired(r.desired)
			authorization.Users = cloneDesired(*d).Users
			authorization.ValidUntil = d.ValidUntil
			authctx, cancel := context.WithTimeout(ctx, 20*time.Second)
			e = r.core.Apply(authctx, authorization)
			cancel()
			if e != nil {
				return e
			}
			if e = r.saveDesired(authorization); e != nil {
				return e
			}
		}
	}
	applyctx, cancel := context.WithTimeout(ctx, 20*time.Second)
	defer cancel()
	if e = r.core.Apply(applyctx, *d); e != nil {
		return e
	}
	return r.saveDesired(*d)
}

func (r *Runtime) saveDesired(d Desired) error {
	if e := r.store.SaveDesired(d); e != nil {
		r.core.Stop()
		return e
	}
	r.desired = cloneDesired(d)
	return nil
}
func (r *Runtime) sample(ctx context.Context) error {
	c, e := r.core.Counters(ctx)
	if e != nil {
		return e
	}
	return r.store.Sample(r.core.Generation(), c)
}
func (r *Runtime) trafficLoop(ctx context.Context) {
	for {
		if e := r.traffic(ctx); e != nil {
			log.Print("traffic report deferred: ", e)
		}
		r.mu.Lock()
		_, interval := snapshotIntervals(r.desired)
		r.mu.Unlock()
		timer := time.NewTimer(interval)
		select {
		case <-ctx.Done():
			timer.Stop()
			return
		case <-timer.C:
		}
	}
}
func (r *Runtime) traffic(ctx context.Context) error {
	r.mu.Lock()
	var sampleErr error
	if r.core.Running() {
		samplectx, cancel := context.WithTimeout(ctx, 5*time.Second)
		sampleErr = r.sample(samplectx)
		cancel()
	}
	r.mu.Unlock()
	reportctx, cancel := context.WithTimeout(ctx, 20*time.Second)
	defer cancel()
	for i := 0; i < 10; i++ {
		if e := reportctx.Err(); e != nil {
			return e
		}
		body, e := r.store.Pending()
		if e != nil {
			return e
		}
		if len(body) == 0 {
			break
		}
		uuid, e := r.panel.Send(reportctx, body)
		if e != nil {
			return e
		}
		if e = r.store.Ack(uuid); e != nil {
			return e
		}
	}
	return sampleErr
}
func (r *Runtime) sampleLoop(ctx context.Context) {
	ticker := time.NewTicker(10 * time.Second)
	defer ticker.Stop()
	for {
		select {
		case <-ctx.Done():
			return
		case <-ticker.C:
			r.mu.Lock()
			if r.core.Running() {
				samplectx, cancel := context.WithTimeout(ctx, 5*time.Second)
				e := r.sample(samplectx)
				cancel()
				if e != nil {
					log.Print("local traffic sample failed: ", e)
				}
			}
			r.mu.Unlock()
		}
	}
}

func snapshotIntervals(d Desired) (time.Duration, time.Duration) {
	return time.Duration(clampInterval(d.PollIntervalSeconds, 5, 2, 300)) * time.Second, time.Duration(clampInterval(d.TrafficIntervalSeconds, 60, 10, 3600)) * time.Second
}
func clampInterval(value, fallback, min, max int) int {
	if value == 0 {
		return fallback
	}
	if value < min {
		return min
	}
	if value > max {
		return max
	}
	return value
}
