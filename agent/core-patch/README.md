# V2Fly port statistics patch

The patch targets official V2Fly **v5.53.0**, commit
`b53bebbb859f2970e99432d53047a1bb8620e987`. It does not include Xray code.
Upgrading upstream requires
reapplying and retesting the patch and reviewing upstream security fixes.

Agents performing an upstream upgrade must follow [the upgrade runbook](../../docs/agents/v2fly-upgrade.md), including synchronized Agent version checks, gray rollout, and preserved traffic state during rollback.

## Build

Install Git and Go with automatic toolchain download available, then:

```sh
./agent/core-patch/build.sh /tmp/v2ray-yap
/tmp/v2ray-yap version
```

The build defaults to Go 1.26.8 (`GOTOOLCHAIN` can override it). Upstream declares
Go 1.25.5 minimum and toolchain Go 1.26.1; the security dependency patch requires
Go 1.26.0 or newer. The build verifies the exact source commit, checks patch application, runs focused
counter tests, and builds a static binary. Go downloads upstream dependencies using
the patched `go.mod` and `go.sum`. The separate security dependency patch pins
gRPC 1.83.2, x/crypto 0.56.0, and klauspost/compress 1.18.7, with their required
transitive updates. It does not change the upstream source revision or statistics
contract. Set `GOCACHE` and `GOMODCACHE` if default cache
locations are not writable. No downloaded core source is vendored in this repository.
Cross compilation requires running the tests with host settings before a separate
target build; the convenience script is intended for native builds.

## Contract

- Managed inbound tags must begin with `yap-`.
- Users retain authentication emails `user-N` for add/remove API operations.
- For opt-in TCP inbound traffic the dispatcher counter identity becomes
  `user-N-port-P`, where P is the local gateway/listener port.
- Counter names are `user>>>user-N-port-P>>>traffic>>>uplink` and
  `user>>>user-N-port-P>>>traffic>>>downlink`.
- A shared VMess handler with several listening ports keeps one authentication
  list. No user duplication is introduced by this patch.
- Enable `statsUserUplink` and `statsUserDownlink` for the relevant user policy level.
- Read cumulative counters with reset disabled. A core restart starts a new
  counter generation; the Agent must distinguish generations explicitly.
- The version output includes `yap-port-stats-v1`; the Agent rejects unmarked cores.
- Unmanaged tags, UDP and missing gateway ports preserve upstream counter names.
- Since counter names contain no inbound tag, managed ports must be unique across
  this core instance, even when using different listen addresses.

## Verification and boundaries

On v5.53.0, patch application against the clean pinned commit, the complete
dispatcher test package, and stock/patched builds passed using Go 1.26.8. Both
version outputs were checked, including the patched capability marker.

The included test writes actual dispatcher links and reads registered counters:
separate ports remain separate, subsequent traffic accumulates, both directions
are counted, the authentication email is unchanged, and non-opt-in/UDP/zero-port
traffic retains upstream behavior. The build-time unit test is not an end-to-end VMess network test.

Agent real-network integration on v5.53.0 verifies shared-handler per-port counters, dynamic UUID rotation, user removal, and restart restoration. Deleting a user rejects new authentication while an established HTTP stream continues. The performance harness also verifies per-port cumulative bytes during a balanced two-port workload; details and measured results are in `../README.md`.

This patch changes statistics labels only. It does not alter authentication, handler APIs, connection termination, routing or transport behavior. UDP attribution and real traffic through multiplexed transports remain outside acceptance scope. Complete Agent-plus-core memory under production load still needs host-specific validation. No 256 MB capacity guarantee is implied by this artifact.

The full upstream binary also includes optional protocols and tooling. Public
advisories for the unused DTLS v2 and unmaintained OpenPGP packages remain in
that binary; the managed VMess/TCP configuration does not enable these paths.
Do not treat this configuration assessment as a vulnerability-free claim for
arbitrary core configurations. Review these dependencies again when enabling
additional protocols or changing the pinned release.
