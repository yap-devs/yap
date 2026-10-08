# YAP node and route operations

Use this runbook when an operator supplies SSH access and requirements for a new physical destination, a public relay entry, or an existing Agent node. Obtain the panel URL and deployment path from that operator. SSH is a provisioning tool; the running panel does not SSH into nodes.

For a core version update, read [V2Fly upgrades](v2fly-upgrade.md). For hosting, cron, queues and backups, read [Deployment](../deployment/README.md). Agent configuration and tests are described in [the Agent README](../../agent/README.md).

## Inspect and map

Record OS, architecture/libc, available memory, service ownership, existing configs, listening ports, firewall and the requested public endpoints. Preserve service/config backups before editing. Keep credentials and user UUIDs out of reports.

One `nodes` record represents one physical Agent-managed core. Each public billing entry is a `node_routes` record. Direct access requires one route too. A relay's advertised address/port may differ from the destination's `listen_port`; record both explicitly. A relay service is provisioned independently from the destination Agent.

Managed listen ports must be unique per node. Routes sharing an `inbound_tag` share a single user list and require contiguous ports with identical `for_low_priority` permissions. Use separate tags for noncontiguous groups. Disabling a middle port of an enabled shared range is rejected. Tags start with `yap-`. Route identity (`node_id`, `inbound_tag`, `listen_port`) is immutable; create a replacement route when that identity changes.

The supported protocol is plain TCP VMess. TLS, other transports and protocols need explicit Agent/subscription extensions before use. UDP and mux attribution are outside the validated contract.

## Provision

1. Build the pinned patched core with `agent/core-patch/build.sh`. Check its version and the `yap-port-stats-v1` marker. Build the Agent from `agent/` with `go build -trimpath -o yap-agent ./cmd/yap-agent`. SQLite requires CGO and a C compiler on the build machine; check the resulting binary's architecture and libc dependencies on the destination.
2. Create one disabled Node and its routes in Filament. Define public addresses, listen ports, handler tags, permissions and rates from the operator's requirements. Enable `NODE_AGENT_ENABLED=true` on the panel.
3. Issue the node token on the panel with `php artisan nodes:issue-token NODE_ID --no-interaction`. This command prints a secret: capture it privately and install it in the Agent's mode-0600 configuration, using the actual HTTPS panel URL. Never include its output in reports.
4. Install binaries, a dedicated service account and private writable state directory. Use the configuration schema in the Agent README. Set `core_socket_path` to a filesystem Unix socket in the private state directory (mode 0700); the management socket is mode 0600 and has no TCP listener. Open only intended public listener ports. The Agent owns its core child process; stop any conflicting old core before starting the Agent.
5. Create a systemd service using `User=`, `WorkingDirectory=`, and `ExecStart=/usr/local/bin/yap-agent -config /etc/yap-agent/config.json`, with `Restart=on-failure`. Allow graceful shutdown time for final sampling. Keep persistent SQLite/config paths outside replaceable release directories. Match file ownership to the service account.
6. Enable the prepared routes and node, start the Agent, and inspect service logs, applied configuration revision and last-seen time. Configure each relay to its intended destination port; validate its config and inspect whether the installed relay version actually supports reload before selecting reload over restart.

Provisioning an existing node requires preserving its old binaries, configuration and SQLite state first. Do not run two cores on the same managed ports or start two Agents against the same state file.

## Acceptance

Verify every public entry independently: listener connectivity, VMess authentication, expected subscription metadata, distinct port counters, raw traffic receipts and matching ACKs. Compare Agent revision and reported core version with the panel. Verify the private gRPC socket permissions and absence of a TCP management listener.

Run business and fault tests in the sandbox: UUID rotation, user removal, debt/recharge, duplicate batch billing, restarts, node disable/re-enable and temporary panel outage. Node disable or token revocation returns HTTP 401, which stops the core and clears saved authorization while preserving pending traffic. Temporary connectivity failures and server errors preserve the last applied configuration. Do not create synthetic production registrations or payments. Production checks use necessary node operations and read-only health observations.

User-only updates use local gRPC. Structural listener changes validate a candidate configuration and restart the core, interrupting active connections. User removal denies new authentication but does not disconnect an established stream. Rates are maintained by the panel; nodes report raw bytes. Panel configuration polling and traffic reporting have independent intervals, documented in the Agent README.

Measure memory for the intended user count and workload; historical figures in the Agent README are not capacity guarantees. A 256 MiB node cannot be assumed to support thousands of users or large connection counts without measurement.

## Recovery and handoff

Keep SQLite state across upgrades: it holds baselines, pending totals and immutable retry batches. Do not delete it to resolve a transient failure. A core crash can lose bytes since the last local sample; a panel outage preserves the last applied authorization until a fresh snapshot arrives. Database recovery can lose already acknowledged traffic since the restored backup.

Restore a compatible Agent/core pair when necessary, preserving state and pending receipts. There is no fallback to panel-initiated SSH collection. Do not reset counters or switch traffic ownership during binary rollback.

Report the node/route IDs, advertised endpoints and destination ports, release checksums, service health, observed revision, traffic acceptance, restart interruption and outstanding issues. Report private backup locations only to the operator. Do not commit credentials, production inventories or deployment history.
