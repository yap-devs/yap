<?php

namespace App\Services;

use App\Models\Node;
use App\Models\NodeRoute;
use App\Models\TrafficBatch;
use App\Models\TrafficRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OverflowException;

class TrafficIngestionService
{
    /** @param array<int, array{user_id: int, route_id: int, uplink: int, downlink: int}> $records */
    public function ingest(Node $node, string $batch_uuid, array $records): TrafficBatch
    {
        $authenticated_token_hash = (string) $node->agent_token_hash;
        $batch_uuid = strtolower($batch_uuid);
        $records = array_map(fn (array $row): array => [
            'user_id' => (int) $row['user_id'], 'route_id' => (int) $row['route_id'],
            'uplink' => (int) $row['uplink'], 'downlink' => (int) $row['downlink'],
        ], $records);
        usort($records, fn (array $a, array $b): int => [$a['user_id'], $a['route_id']] <=> [$b['user_id'], $b['route_id']]);
        $keys = array_map(fn (array $row): string => $row['user_id'].':'.$row['route_id'], $records);
        if (count(array_unique($keys)) !== count($keys)) {
            throw ValidationException::withMessages(['records' => 'Duplicate user and route in a batch.']);
        }
        $payload_hash = hash('sha256', json_encode($records, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($node, $batch_uuid, $records, $payload_hash, $authenticated_token_hash): TrafficBatch {
            $node = Node::whereKey($node->id)->lockForUpdate()->firstOrFail();
            abort_unless($node->enabled, 401);
            abort_unless(hash_equals((string) $node->agent_token_hash, $authenticated_token_hash), 401);
            $existing = TrafficBatch::withTrashed()->where('node_id', $node->id)->where('batch_uuid', $batch_uuid)->first();
            if ($existing) {
                abort_unless(hash_equals($existing->payload_hash, $payload_hash), 409, 'Batch contents differ from the committed receipt.');

                return $existing;
            }

            $routes = NodeRoute::withTrashed()->where('node_id', $node->id)
                ->whereIn('id', array_column($records, 'route_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $users = User::withTrashed()->whereIn('id', array_column($records, 'user_id'))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $users->load(['packages' => fn ($query) => $query->active()->orderBy('id')->lockForUpdate()]);
            $batch = TrafficBatch::create([
                'node_id' => $node->id, 'batch_uuid' => strtolower($batch_uuid),
                'payload_hash' => $payload_hash, 'received_at' => now(),
            ]);
            $details = [];
            foreach ($records as $record) {
                $route = $routes->get($record['route_id']);
                $user = $users->get($record['user_id']);
                if (! $route || ! $user) {
                    throw ValidationException::withMessages(['records' => 'Unknown user or route outside this node.']);
                }
                try {
                    $up = TrafficRate::bill($record['uplink'], $route->rate);
                    $down = TrafficRate::bill($record['downlink'], $route->rate);
                    $user->traffic_uplink = $this->add($user->traffic_uplink, $up);
                    $user->traffic_downlink = $this->add($user->traffic_downlink, $down);
                    $user->traffic_unpaid = $this->add($this->add($user->traffic_unpaid, $up), $down);
                } catch (OverflowException) {
                    throw ValidationException::withMessages(['records' => 'Traffic exceeds the supported counter range.']);
                }
                $details[] = [
                    'traffic_batch_id' => $batch->id, 'user_id' => $user->id, 'node_route_id' => $route->id,
                    'raw_uplink' => $record['uplink'], 'raw_downlink' => $record['downlink'],
                    'applied_rate' => $route->rate, 'billed_uplink' => $up, 'billed_downlink' => $down,
                    'created_at' => now(), 'updated_at' => now(),
                ];
            }
            foreach (array_chunk($details, 100) as $chunk) {
                TrafficRecord::insert($chunk);
            }
            foreach ($users as $user) {
                $user->save();
                if (! $user->trashed()) {
                    app(TrafficBillingService::class)->settleLocked($user);
                }
            }

            return $batch;
        }, 3);
    }

    private function add(int $left, int $right): int
    {
        if ($left > PHP_INT_MAX - $right) {
            throw new OverflowException('Traffic counter overflow.');
        }

        return $left + $right;
    }
}
