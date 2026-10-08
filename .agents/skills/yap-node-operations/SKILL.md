---
name: yap-node-operations
description: Provision or update YAP Linux Agent nodes, destinations and relay routes from operator-provided SSH access and endpoint requirements. Also guides pinned V2Fly patch upgrades.
---

# YAP node operations

For new machines, destinations or relay entries, read [node operations](../../../docs/agents/node-operations.md) and the [Agent configuration](../../../agent/README.md). Obtain the panel URL, SSH target and required public endpoints from the task context; inspect current services before choosing a port mapping.

For upstream V2Fly updates, read [the upgrade runbook](../../../docs/agents/v2fly-upgrade.md). Build and verify the matching Agent/core pair before replacing running binaries. A host's build resource limit is task context, not a repository requirement.

When changing the panel's `NODE_POLL_INTERVAL_SECONDS` or `NODE_TRAFFIC_INTERVAL_SECONDS`, follow [Updating Agent intervals](../../../docs/deployment/README.md#updating-agent-intervals): rebuild configuration with `php artisan config:cache --no-interaction`, then clear the effective `node_agent.snapshot_store` using `php artisan cache:clear <store> --no-interaction` (`file` by default). A bare `cache:clear` may target a different store. Reload persistent application processes and restart persistent queue workers after clearing the cache. Verify enabled nodes apply the new snapshot revision and save both requested interval values; allow the previous poll interval for delivery. Read only revision/interval fields, omit credentials, and preserve Agent SQLite state.

Preserve these invariants:

- One physical Agent-managed core is one Node. Every advertised entry, including direct access, has a Node Route.
- Shared handler ports are contiguous and use identical authorization permissions. Use separate handler tags for noncontiguous groups. Only plain TCP VMess is currently supported.
- Configure management gRPC with `core_socket_path`: a mode-0600 filesystem Unix socket in a mode-0700 directory, with no TCP management listener. Keep tokens private and SQLite state durable across restarts and upgrades. The Agent starts its core; do not leave a conflicting independent core running.
- SSH provisions the machine. Configuration polling and raw traffic reporting run from the Agent to the panel over HTTPS.
- Panel business acceptance uses the sandbox. Production verification uses necessary node operations and read-only health checks; do not create synthetic registrations or payments.

Use the operator's existing authorization within its stated scope. Prepare rollback artifacts before service replacement. Finish with actual node/route mapping, validation evidence, deployment status and any unresolved limitations, omitting credentials.
