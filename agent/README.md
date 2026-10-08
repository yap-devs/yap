# YAP node agent

Linux agent for an agent-owned, patched V2Fly 5.53.0 process (Go 1.25.5 minimum; pinned build toolchain Go 1.26.8). Build requires a C compiler (SQLite uses CGO):

```sh
go build -trimpath -o yap-agent ./cmd/yap-agent
go test ./...
YAP_TEST_CORE_BINARY=/path/to/patched/v2ray go test ./...
```

Build the matching patched core using `core-patch/`. Stock V2Fly is rejected: its `version` output must contain `yap-port-stats-v1`.

Create a private local file with mode 0600, for example `/etc/yap-agent/config.json`:

```json
{
  "panel_url": "https://panel.example",
  "token": "REPLACE_WITH_NODE_TOKEN",
  "state_path": "/var/lib/yap-agent/state.sqlite",
  "core_binary": "/usr/local/bin/yap-v2ray",
  "core_config_path": "/var/lib/yap-agent/core.json",
  "core_socket_path": "/var/lib/yap-agent/api.sock"
}
```

Use a dedicated account and private writable state directory. Start with `yap-agent -config /etc/yap-agent/config.json`. The agent accepts no executable or command strings from the panel. HTTPS certificate verification is mandatory and redirects are rejected. Core gRPC uses a mode-0600 filesystem Unix socket in a mode-0700 directory; there is no TCP management listener for subscribers to reach through the proxy. Set `core_socket_path` to a clean absolute path shorter than 108 bytes. Do not run another core on the same ports. Linux parent-death signaling kills the child if the agent crashes; normal shutdown takes a final counter sample before stopping it.

Configuration defaults to a 5-second interval with ±10% jitter. Snapshot `poll_interval_seconds` is clamped to 2–300 seconds and `traffic_interval_seconds` to 10–3,600 seconds; omitted/zero values use defaults of 5/60 seconds. Updated intervals apply when the next loop timer is created. Local cumulative counters are sampled every 10 seconds; traffic uploads run separately every 60 seconds. Each upload tick drains up to 10 batches within 20 seconds. A persisted round-robin cursor prevents newly active early user IDs from starving later IDs. The complete desired snapshot is persisted and restored before contacting the panel. `valid_until` is a refresh hint, not an offline authorization lease: panel outages preserve the last applied authorization, including after package expiry, until a fresh snapshot arrives. An explicit configuration HTTP 401 revokes node authorization: the agent takes a best-effort final sample, stops the core and clears its saved desired snapshot. Pending traffic remains durable and retries once the panel accepts it; re-enabling the node requires a fresh snapshot. A failed final sample does not prevent revocation.

This phase supports plain TCP VMess only, matching the panel subscription metadata. WebSocket, gRPC, TLS and other managed transport/security modes are rejected; adding them requires explicit protocol and subscription extensions. Managed VMess inbound tags must start with `yap-`. Routes sharing a handler must have contiguous listen ports and identical `for_low_priority` authorization. Normal users are admitted everywhere; low-priority users only on eligible handlers. User credentials are stored once per handler. Noncontiguous ports must use separate handler groups; the agent rejects holes rather than opening unlisted ports. User-only updates use local gRPC add/remove; structural updates validate a candidate file, then sample and durably persist the old generation immediately before restarting the core. A failed final sample aborts the restart and preserves the running process. Real VMess tests confirmed that UUID rotation and deletion reject new authentication, but deletion does not disconnect an established stream.

The core patch labels cumulative counters `user>>>user-ID-port-PORT>>>traffic>>>uplink/downlink`. The agent never resets counters. SQLite transactions persist counter baselines, deltas, and one immutable UUID batch. Retries resend the exact body; only a matching accepted ACK deletes that batch. New deltas remain separate during retries. WAL and FULL synchronous mode protect committed state. One local file lock excludes duplicate agents.

Statistics queries are scoped to each managed listener port. Responses remain
bounded to 4 MiB; oversized groups are split by user ID prefixes, including
historical users whose counters remain after removal. A sample is persisted only
after all partitions have succeeded. Sampling failures do not block user
revocation or UUID rotation through HandlerService. When an update also changes
the topology, the new user list is saved on the existing topology first; its old
revision keeps the full update pending until final sampling permits a restart.
User synchronization also tolerates lost successful HandlerService responses:
an already absent user can be removed again, and additions replace any partially
applied identity before installing the desired UUID. Other RPC failures remain
errors and do not acknowledge the update.

A core crash can lose traffic since the last successful sample (normally at most the 10-second sampling interval). Each newly owned process receives a new generation; its counters are counted from zero without reusing the previous baseline. Keep state files when upgrading or restarting. No remote database access, remote shell, production deployment, or service installation is performed by this module.

Historical baseline measurement (Linux amd64, patched core 5.16.1, Go 1.22.3, one shared handler with two ports, no client traffic): 1,000 users peaked at 88,832 KiB RSS; 10,000 users peaked at 425,856 KiB RSS. The 10,000-user case exceeds a 256 MiB node budget before accounting for the agent, OS, connections, or accumulated statistics. These figures establish a limitation, not a deployment capacity guarantee. Repeat with `YAP_TEST_MEMORY=1 YAP_TEST_CORE_BINARY=/path/to/patched/v2ray go test -v ./internal/agent -run TestPatchedCoreMemory` on the intended host.

Each traffic batch contains at most 1,000 records and stays below the panel's 1 MiB JSON limit; excess aggregates remain durable for subsequent batches.

Planned restarts and graceful shutdowns still have a small final-sample-to-process-stop window: V2Fly has no quiesce-and-final-counter API, so bytes transferred in that window can be lost. The after-validation sample excludes candidate-validation time from this window; it does not guarantee zero-loss shutdown.

Performance comparison is opt-in. Use the same fixed client binary for each server version and run the server versions sequentially, without race instrumentation or concurrent builds:

```sh
YAP_TEST_PERFORMANCE=1 \
  YAP_TEST_CLIENT_BINARY=/path/to/fixed/client/v2ray \
  YAP_TEST_CORE_BINARY=/path/to/server/v2ray \
  go test -count=1 -timeout=15m -v ./internal/agent -run '^TestCorePerformance$'
```

Each of the 1,000/10,000 configured-user cases runs three iterations by default, with two shared ports, four concurrent transfers and 128 requests of 4 MiB (512 MiB per iteration). One user generates traffic; this is not a test of thousands of simultaneously active users. JSON output includes validation/startup time, core CPU, idle/loaded RSS, peak RSS and payload throughput. Stock releases use native user counters; patched releases additionally verify port attribution. Loopback throughput is a local comparison, not an Internet line-speed prediction.

Measurements on 2026-10-06 (Linux amd64, fixed v5.53.0 stock client; three iterations per case):

| Server | Users | Idle RSS MiB | Peak RSS MiB | Validation + readiness seconds | Payload MiB/s |
|---|---:|---:|---:|---:|---:|
| v5.16.1 patched / Go 1.22.3 | 1,000 | 76.6 | 110.3 | 1.53 | 247.0 |
| v5.53.0 stock / Go 1.26.8 | 1,000 | 72.8 | 110.9 | 1.42 | 250.9 |
| v5.53.0 patched / Go 1.26.8 | 1,000 | 72.9 | 111.1 | 1.44 | 259.1 |
| v5.16.1 patched / Go 1.22.3 | 10,000 | 367.1 | 414.1 | 9.80 | 223.6 |
| v5.53.0 stock / Go 1.26.8 | 10,000 | 321.5 | 391.0 | 9.00 | 225.6 |
| v5.53.0 patched / Go 1.26.8 | 10,000 | 307.4 | 392.0 | 8.91 | 201.1 |

These are per-metric medians, with peak RSS including startup. The old-version comparison includes a toolchain change. The latest stock/patched pair uses the same toolchain. The 10,000-user throughput difference remains unconfirmed and requires a controlled repeat. Do not claim zero patch overhead or a confirmed regression from this dataset. Core memory alone already exceeds 256 MiB at 10,000 users; agent and OS memory are additional.

The complete panel integration is also opt-in. From `agent/`, set `YAP_TEST_PANEL_ROOT` to the absolute repository root and provide the matching patched core:

```sh
YAP_TEST_PANEL_ROOT=/absolute/path/to/yap \
  YAP_TEST_CORE_BINARY=/path/to/patched/v2ray \
  go test -count=1 -timeout=5m -v ./internal/agent -run '^TestLaravelPanelEndToEnd$'
```

This test launches the PHP fixture in `tests/Fixtures/AgentPanelRouter.php` and routes real agent requests through a trusted local TLS proxy to Laravel's HTTP kernel. It creates an exclusive temporary SQLite database and private cache, never loads the project's `.env`, and blocks external notifications/jobs/HTTP services. Real VMess bytes verify separate entry rates, exact billed totals, package exhaustion, a committed batch whose ACK is lost, unchanged retry billing after a rate edit, subscription cache invalidation, denial while unpaid, old-user recharge and first recharge for a new user through the real payment fulfillment service, and idempotent hourly display aggregation. It invokes the production runtime reconciliation/reporting methods explicitly for deterministic timing; periodic loop timing is covered by the separate runtime tests. Payment gateway signature verification and actual provider calls are outside this local fixture.
