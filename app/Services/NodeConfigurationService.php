<?php

namespace App\Services;

use App\Models\Node;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class NodeConfigurationService
{
    public function snapshot(Node $node): array
    {
        $authenticated_token_hash = (string) $node->agent_token_hash;
        $node = Node::whereKey($node->id)->firstOrFail();
        abort_unless($node->enabled && hash_equals((string) $node->agent_token_hash, $authenticated_token_hash), 401);
        $store = Cache::store(config('node_agent.snapshot_store'));
        $key = 'node-snapshot:'.$node->id;
        $snapshot = $store->get($key);
        if (is_array($snapshot) && $snapshot['revision'] === $node->desired_revision && $snapshot['valid_until'] > now()->timestamp) {
            return $snapshot;
        }

        return DB::transaction(function () use ($node, $store, $authenticated_token_hash): array {
            $node = Node::whereKey($node->id)->lockForUpdate()->firstOrFail();
            abort_unless($node->enabled && hash_equals((string) $node->agent_token_hash, $authenticated_token_hash), 401);
            $key = 'node-snapshot:'.$node->id;
            $snapshot = $store->get($key);
            if (is_array($snapshot) && $snapshot['revision'] === $node->desired_revision && $snapshot['valid_until'] > now()->timestamp) {
                return $snapshot;
            }
            // Expiry transitions must produce a new revision even without a write.
            $node->increment('desired_revision');
            $key = 'node-snapshot:'.$node->id;
            $routes = $node->routes()->where('enabled', true)->orderBy('id')->get();
            $users = User::with(['packages' => fn ($query) => $query->active()])->get();
            $valid_until = now()->addDay()->startOfDay()->timestamp;
            foreach ($users as $user) {
                foreach ($user->packages as $package) {
                    foreach ([$package->started_at, $package->ended_at] as $time) {
                        if ($time && $time->isFuture()) {
                            $valid_until = min($valid_until, $time->timestamp);
                        }
                    }
                }
            }
            $snapshot = [
                'revision' => $node->desired_revision,
                'valid_until' => $valid_until,
                'core_config' => $node->core_config ?? new \stdClass,
                'poll_interval_seconds' => config('node_agent.poll_interval_seconds'),
                'traffic_interval_seconds' => config('node_agent.traffic_interval_seconds'),
                'routes' => $routes->map(fn ($route): array => $route->only(['id', 'inbound_tag', 'listen_port', 'for_low_priority']))->values()->all(),
                'users' => $users->filter(fn (User $user): bool => $user->is_valid)->map(fn (User $user): array => [
                    'id' => $user->id, 'uuid' => $user->uuid, 'email' => $user->v2rayStatsLabel(),
                    'low_priority' => $user->is_low_priority,
                ])->values()->all(),
            ];
            DB::afterCommit(fn () => $store->put($key, $snapshot, now()->addDays(2)));

            return $snapshot;
        }, 3);
    }
}
