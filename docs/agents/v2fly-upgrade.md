# V2Fly upstream upgrade runbook

Use this runbook when the owner asks an agent to upgrade the managed V2Fly core. Complete the local patch, build, and review work before scheduling a node update. Node provisioning is described in [node-operations.md](node-operations.md).

## Current contract

The current baseline is V2Fly `v5.53.0`, source commit `b53bebbb859f2970e99432d53047a1bb8620e987`, built with Go `1.26.8`. Treat these as the current repository pins, not as a permanent claim about the latest upstream release. Discover the release again for every upgrade.

The patch changes dispatcher statistics labels for managed TCP inbounds only:

```text
Authentication email: user-N
Counter: user>>>user-N-port-P>>>traffic>>>uplink/downlink
```

The port comes from the inbound connection's gateway/listener port. Tags must begin with `yap-`; managed ports are unique per core process. Shared handlers retain one user list and currently require contiguous ports and identical low-priority permissions. The current agent/subscription implementation supports plain TCP VMess. UDP and mux attribution are outside the validated contract.

Do not replace this with per-port copies of user credentials, reset cumulative counters, enable an untested transport, or let nodes automatically install unpatched upstream releases.

The agent currently requires both the exact core version and the `yap-port-stats-v1` marker. Upgrade the agent/core pair together. Keep the marker unchanged only while its statistics contract remains compatible; a changed contract requires coordinated Agent changes.

## 1. Select and inspect an official release

1. Read the official latest-release API: `https://api.github.com/repos/v2fly/v2ray-core/releases/latest`. Record `tag_name`, `published_at`, `prerelease`, `draft`, and `html_url`. Require a published, non-draft, non-prerelease release.
2. Read the release notes and changes since the repository's pinned version. Review security fixes, minimum Go/toolchain requirements, dispatcher/session fields, inbound listeners, VMess authentication, HandlerService, and StatsService.
3. Clone the official release tag into a temporary directory. Record the resolved commit with `git rev-parse HEAD`; do not pin only a moving branch or infer the commit from `target_commitish`.
4. Check the current working tree before editing. Preserve unrelated work; never reset the repository or overwrite deployment secrets.

Use only official upstream source/release artifacts. Record the source commit and toolchain alongside the resulting binaries. If an urgent security fix is involved, report its relevance and prioritize the verified upgrade; do not skip validation silently.

## 2. Port the patch on clean source

1. Run `git apply --check` against the clean new release. Inspect the affected code even when application succeeds: matching context does not prove matching behavior.
2. If it conflicts, port the small change to the new dispatcher implementation. Preserve the authentication email and generate a separate statistics identity using the actual gateway port. Review nil handling and TCP/tag opt-in behavior.
3. Port the dispatcher regression test. Verify independent ports, cumulative bytes, uplink/downlink, unchanged user email, and unchanged unmanaged/UDP/zero-port behavior.
4. Set the build marker and generate a patch against the clean release, including the test. Update the patch filename and remove the obsolete active patch only after the replacement is verified.
5. Confirm the new patch applies cleanly to a second clean checkout or worktree at the same commit.

Do not expand the patch to change connection termination, protocol behavior, arbitrary port lists, or authentication without a separate design and acceptance scope.

## 3. Update repository pins together

Inspect and update all of these locations:

- `agent/core-patch/build.sh`: release tag, exact commit, patch filename, and compatible default build toolchain.
- `agent/core-patch/v2fly-<version>-port-stats.patch`: dispatcher change, capability marker, and tests.
- `agent/core-patch/v2fly-<version>-security-deps.patch`: separately pinned dependency fixes and generated checksums; recheck public advisories for the candidate release.
- `agent/go.mod` and `agent/go.sum`: V2Fly API dependency and required transitive/toolchain changes. Use the Go module tools; do not fabricate checksums.
- `agent/internal/agent/core.go`: exact version check and capability compatibility.
- `agent/internal/agent/panel.go`: reported core version. Adjust agent version when releasing a new agent artifact.
- `agent/README.md`, `agent/core-patch/README.md`, and this runbook's current baseline: requirements, measured validation, and remaining limitations.

Search for the old version throughout `agent/` and `docs/agents/`. Keep deliberately labeled historical measurements; remove stale active instructions. Avoid mixing an agent built against one version with an unverified core from another.

## 4. Build and validate locally

From the repository root, build the new core:

```sh
./agent/core-patch/build.sh /tmp/yap-v2ray-candidate
/tmp/yap-v2ray-candidate version
```

Run the complete upstream dispatcher test package on the patched checkout in addition to the build script's focused tests. Confirm the exact version, marker, commit, toolchain, architecture, and build parameters.

From `agent/`, run the following sequentially. Environment cache paths can be set to private writable directories when necessary.

```sh
YAP_TEST_CORE_BINARY=/tmp/yap-v2ray-candidate go test -count=1 ./...
YAP_TEST_CORE_BINARY=/tmp/yap-v2ray-candidate go test -race -count=1 ./...
go vet ./...
go build -trimpath -o /tmp/yap-agent-candidate ./cmd/yap-agent
```

Read the output and distinguish passing tests from skipped tests. Setting `YAP_TEST_CORE_BINARY` is required for actual-core tests; a unit-only pass does not validate the upgrade. Validate:

- Shared-handler per-port statistics through real VMess traffic.
- UUID rotation: old UUID denied, new UUID accepted, without a core restart.
- User removal: new connections denied; report established-session behavior accurately.
- Core restart, agent restart, saved-snapshot restoration during a panel outage, and generation changes.
- Durable counter baselines, immutable batch retries, matching ACKs, batch limits, and fair draining.
- Final pre-restart sampling after candidate validation; failure preserves the running core.

Current measured behavior permits established connections to continue after user removal. Do not promise immediate disconnection. Final sampling followed by process stop has a residual byte-loss window; the core has no atomic quiesce-and-final-counter API.

Performance tests are separate and opt-in. When requested, follow the harness instructions in `agent/README.md`: same host, CPU affinity, workload, fixed client binary, repeated measurements, no concurrent compilation and no race instrumentation. Compare latest stock versus patched binaries built with the same toolchain to assess patch cost. A comparison to an old artifact built with an old toolchain measures the combined release/toolchain change. Preserve uncertainty when results vary.

## 5. Review and prepare one gray node

Before a production change, prepare a concrete package for review: patch and dependency diffs, source/toolchain pins, binary checksums, test output, architecture/libc compatibility, affected nodes, rollback artifacts, and the expected interruption window. Tests use temporary local state; never run a database reset or production migration as part of core validation. Do not automatically commit, merge, publish, or deploy merely because the build passed.

Preserve the prior agent binary, prior core binary, private configuration, and SQLite state. Confirm the new binary's architecture and runtime dependencies on the target host.

1. Select one node whose workload and capacity are suitable for the candidate.
2. Install candidate binaries under separate names. Verify their versions and checksums before selecting them for the service.
3. Schedule the interruption. Gracefully stop the old agent so it takes its final sample, then switch the agent/core pair and start the service. Active connections are interrupted by a process restart.
4. Preserve SQLite state and node identity. Never delete pending batches or reset state to make a failed upgrade appear healthy.
5. Verify service health, private Unix-socket gRPC, configuration revision, reported versions, all public routes, raw traffic ACKs, billing idempotence, and subscription consistency. Use the current Agent configuration schema, including `core_socket_path` in a private directory. Check logs for restart loops and unacknowledged batches.
6. Record actual downtime and observed errors. Expand to further nodes only after the gray node passes its acceptance checks and observation period.

Use the owner's existing deployment authorization. If authorization for production deployment is absent, finish the candidate and review evidence first, then request approval for that concrete deployment.

## 6. Roll back while preserving traffic state

If acceptance fails, stop the candidate gracefully when possible and switch back to the saved, compatible old agent/core pair. Keep the SQLite database and pending batches. Review any local schema change before claiming an old agent can read new state; backup and restore procedures must not replay already acknowledged traffic.

Do not clear panel receipts during a binary rollback. Report the cause, traffic accounting limitations, and remaining pending batches. If the old artifacts or state format are incompatible, prepare an explicit recovery instead of improvising a destructive reset.

## Completion report

Report the selected release and exact commit, patch scope, toolchain and build parameters, test results including skips, artifacts and checksums, deployment status, and any unresolved limitations. Clearly distinguish local upgrade completion from production rollout completion.
