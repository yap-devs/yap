<?php

use App\Models\Node;
use App\Models\NodeRoute;
use App\Models\TrafficBatch;
use App\Models\TrafficRecord;
use App\Models\User;
use App\Services\TrafficIngestionService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(function () {
    Notification::fake();
    config(['node_agent.enabled' => true, 'yap.unit_price' => '0.00']);
    $this->security_token = Str::random(64);
    $this->security_node = Node::factory()->create(['agent_token_hash' => hash('sha256', $this->security_token)]);
    $this->security_route = NodeRoute::factory()->for($this->security_node)->create(['rate' => '1.25']);
    $this->security_user = User::factory()->create(['balance' => '10.00']);
    $this->security_payload = ['batch_uuid' => (string) Str::uuid(), 'records' => [
        ['user_id' => $this->security_user->id, 'route_id' => $this->security_route->id, 'uplink' => 101, 'downlink' => 202],
    ]];
});

test('foreign node routes reject an otherwise valid mixed batch atomically', function () {
    $foreign_route = NodeRoute::factory()->create();
    $this->security_payload['records'][] = ['user_id' => $this->security_user->id, 'route_id' => $foreign_route->id, 'uplink' => 1, 'downlink' => 1];
    $this->withToken($this->security_token)->postJson('/api/agent/v1/traffic', $this->security_payload)->assertUnprocessable();
    expect(TrafficBatch::count())->toBe(0)->and(TrafficRecord::count())->toBe(0)
        ->and((int) $this->security_user->fresh()->traffic_uplink)->toBe(0);
});

test('missing users reject an otherwise valid mixed batch atomically', function () {
    $this->security_payload['records'][] = ['user_id' => 999999, 'route_id' => $this->security_route->id, 'uplink' => 1, 'downlink' => 1];
    $this->withToken($this->security_token)->postJson('/api/agent/v1/traffic', $this->security_payload)->assertUnprocessable();
    expect(TrafficBatch::count())->toBe(0)->and(TrafficRecord::count())->toBe(0)
        ->and((int) $this->security_user->fresh()->traffic_uplink)->toBe(0);
});

test('agents cannot override recorded rates or billing amounts', function (string $field) {
    $this->security_payload['records'][0][$field] = 0;
    $this->withToken($this->security_token)->postJson('/api/agent/v1/traffic', $this->security_payload)->assertUnprocessable();
    expect(TrafficBatch::count())->toBe(0)->and(TrafficRecord::count())->toBe(0);
})->with(['rate', 'applied_rate', 'billed_uplink', 'billed_downlink', 'traffic_batch_id', 'node_id']);

test('counter overflow rolls back preceding valid rows', function () {
    $second_user = User::factory()->create(['balance' => '10.00']);
    $this->security_payload['records'][] = ['user_id' => $second_user->id, 'route_id' => $this->security_route->id, 'uplink' => PHP_INT_MAX, 'downlink' => 0];
    $this->withToken($this->security_token)->postJson('/api/agent/v1/traffic', $this->security_payload)->assertUnprocessable();
    expect(TrafficBatch::count())->toBe(0)->and(TrafficRecord::count())->toBe(0)
        ->and((int) $this->security_user->fresh()->traffic_uplink)->toBe(0)
        ->and((int) $second_user->fresh()->traffic_uplink)->toBe(0);
});

test('numeric strings above floating point precision retain exact raw bytes', function () {
    $bytes = '9007199254740993';
    $this->security_route->update(['rate' => '1.00']);
    $this->security_payload['records'][0]['uplink'] = $bytes;
    $this->security_payload['records'][0]['downlink'] = 0;
    $this->withToken($this->security_token)->postJson('/api/agent/v1/traffic', $this->security_payload)->assertOk();
    expect((string) TrafficRecord::first()->raw_uplink)->toBe($bytes)
        ->and((string) $this->security_user->fresh()->traffic_uplink)->toBe($bytes);
});

test('reordered numeric string retries and uppercase uuids cannot double charge', function () {
    $second_user = User::factory()->create(['balance' => '10.00']);
    $this->security_payload['records'][] = ['user_id' => $second_user->id, 'route_id' => $this->security_route->id, 'uplink' => 7, 'downlink' => 9];
    $this->withToken($this->security_token)->postJson('/api/agent/v1/traffic', $this->security_payload)->assertOk();
    $this->security_payload['batch_uuid'] = strtoupper($this->security_payload['batch_uuid']);
    $this->security_payload['records'] = array_reverse(array_map(fn (array $row): array => array_map(strval(...), $row), $this->security_payload['records']));
    $this->security_route->update(['rate' => '999999.99']);
    $this->withToken($this->security_token)->postJson('/api/agent/v1/traffic', $this->security_payload)->assertOk();
    expect(TrafficBatch::count())->toBe(1)->and(TrafficRecord::count())->toBe(2)
        ->and((int) $this->security_user->fresh()->traffic_uplink)->toBe(126)
        ->and((int) $second_user->fresh()->traffic_uplink)->toBe(8);
});

test('soft deleted receipts retain their replay identity', function () {
    $this->withToken($this->security_token)->postJson('/api/agent/v1/traffic', $this->security_payload)->assertOk();
    TrafficBatch::first()->delete();
    $this->withToken($this->security_token)->postJson('/api/agent/v1/traffic', $this->security_payload)->assertOk();
    $this->security_payload['records'][0]['uplink']++;
    $this->withToken($this->security_token)->postJson('/api/agent/v1/traffic', $this->security_payload)->assertConflict();
    expect(TrafficBatch::withTrashed()->count())->toBe(1)->and(TrafficRecord::count())->toBe(1)
        ->and((int) $this->security_user->fresh()->traffic_uplink)->toBe(126);
});

test('rotated tokens reject new traffic submissions', function () {
    $new_token = Str::random(64);
    $this->security_node->update(['agent_token_hash' => hash('sha256', $new_token)]);
    $this->withToken($this->security_token)->postJson('/api/agent/v1/traffic', $this->security_payload)->assertUnauthorized();
    $this->withToken($new_token)->postJson('/api/agent/v1/traffic', $this->security_payload)->assertOk();
    expect(TrafficBatch::count())->toBe(1);
});

test('rotation after middleware authentication revokes an uncommitted traffic submission', function () {
    $service = Mockery::mock(TrafficIngestionService::class);
    $service->shouldReceive('ingest')->once()->andReturnUsing(function (Node $node, string $batch_uuid, array $records): TrafficBatch {
        $this->security_node->update(['agent_token_hash' => hash('sha256', Str::random(64))]);

        return (new TrafficIngestionService)->ingest($node, $batch_uuid, $records);
    });
    app()->instance(TrafficIngestionService::class, $service);
    $this->withToken($this->security_token)->postJson('/api/agent/v1/traffic', $this->security_payload)->assertUnauthorized();
    expect(TrafficBatch::count())->toBe(0)->and(TrafficRecord::count())->toBe(0)
        ->and((int) $this->security_user->fresh()->traffic_uplink)->toBe(0);
});

test('oversized agent bodies are rejected before ingestion', function () {
    $body = json_encode(['padding' => str_repeat('x', config('node_agent.max_request_bytes'))], JSON_THROW_ON_ERROR);
    $this->call('POST', '/api/agent/v1/traffic', [], [], [], [
        'HTTP_AUTHORIZATION' => 'Bearer '.$this->security_token,
        'HTTP_ACCEPT' => 'application/json',
        'CONTENT_TYPE' => 'application/json',
    ], $body)->assertStatus(413);
    expect(TrafficBatch::count())->toBe(0)->and(TrafficRecord::count())->toBe(0);
});

test('record limits reject oversized batches without writing receipts', function () {
    $this->security_payload['records'] = array_fill(0, config('node_agent.max_batch_records') + 1, $this->security_payload['records'][0]);
    $this->withToken($this->security_token)->postJson('/api/agent/v1/traffic', $this->security_payload)->assertUnprocessable();
    expect(TrafficBatch::count())->toBe(0)->and(TrafficRecord::count())->toBe(0);
});

test('unsupported counter representations cannot bypass validation', function (mixed $value) {
    $this->security_payload['records'][0]['uplink'] = $value;
    $this->withToken($this->security_token)->postJson('/api/agent/v1/traffic', $this->security_payload)->assertUnprocessable();
    expect(TrafficBatch::count())->toBe(0)->and(TrafficRecord::count())->toBe(0);
})->with([['1e9'], ['9223372036854775808'], ['-1'], [['bytes' => 1]], [true]]);
