<?php

use App\Models\Node;
use App\Models\NodeRoute;
use App\Models\TrafficBatch;
use App\Models\TrafficRecord;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

test('a node has independent direct and relay routes with decimal rates', function () {
    $node = Node::factory()->create();
    NodeRoute::factory()->for($node)->create(['rate' => '1.25', 'listen_port' => 10001]);
    NodeRoute::factory()->for($node)->create(['rate' => '3.00', 'listen_port' => 10002]);

    expect($node->routes()->count())->toBe(2)
        ->and($node->routes()->orderBy('listen_port')->first()->rate)->toBe('1.25')
        ->and($node->toArray())->not->toHaveKey('agent_token_hash');
});

test('a deleted batch still prevents replay of its identity', function () {
    $batch = TrafficBatch::factory()->create();
    $batch->delete();

    expect(fn () => TrafficBatch::factory()->create([
        'node_id' => $batch->node_id,
        'batch_uuid' => $batch->batch_uuid,
    ]))->toThrow(QueryException::class);
});

test('a billing entry cannot be reused by another route including after deletion', function () {
    $route = NodeRoute::factory()->create();
    $route->delete();

    expect(fn () => NodeRoute::factory()->create([
        'node_id' => $route->node_id,
        'inbound_tag' => $route->inbound_tag,
        'listen_port' => $route->listen_port,
    ]))->toThrow(QueryException::class);
});

test('records preserve both raw and billed counters without altering display stats', function () {
    $record = TrafficRecord::factory()->create(['raw_uplink' => 101, 'billed_uplink' => 126, 'applied_rate' => '1.25']);

    expect($record->fresh()->applied_rate)->toBe('1.25')
        ->and($record->batch->node->exists)->toBeTrue()
        ->and(Schema::getColumnListing('user_stats'))->not->toContain('node_route_id', 'raw_uplink');
});

test('route identity cannot change after creation while public fields remain editable', function (string $field, mixed $value) {
    $route = NodeRoute::factory()->create();
    expect(fn () => $route->update([$field => $value]))->toThrow(ValidationException::class);
    $route->refresh()->update(['name' => 'New name', 'rate' => '2.50', 'server' => 'relay.example.com', 'port' => 443]);
    expect($route->fresh()->rate)->toBe('2.50');
})->with([
    'node' => ['node_id', 9999],
    'handler' => ['inbound_tag', 'yap-other'],
    'port' => ['listen_port', 9999],
]);

test('node factory stores only current agent metadata', function () {
    $node = Node::factory()->create();
    expect($node->getAttributes())->not->toHaveKeys(['traffic_source', 'legacy_internal_server'])
        ->and($node->enabled)->toBeTrue();
});

test('core configuration structure changes persist and invalidate authorization', function (string $before, string $after) {
    config(['node_agent.enabled' => true]);
    $node = Node::factory()->create(['core_config' => json_decode($before, false, 512, JSON_THROW_ON_ERROR)]);
    $revision = $node->fresh()->desired_revision;

    $node->core_config = json_decode($after, false, 512, JSON_THROW_ON_ERROR);
    expect($node->isDirty('core_config'))->toBeTrue();
    $node->save();

    expect(json_encode($node->fresh()->core_config))->toBe($after)
        ->and($node->fresh()->desired_revision)->toBeGreaterThan($revision);
})->with([
    'empty object to list' => ['{"settings":{}}', '{"settings":[]}'],
    'empty list to object' => ['{"settings":[]}', '{"settings":{}}'],
    'numeric keys to list' => ['{"policy":{"levels":{"0":{"handshake":8}}}}', '{"policy":{"levels":[{"handshake":8}]}}'],
    'list to numeric keys' => ['{"policy":{"levels":[{"handshake":8}]}}', '{"policy":{"levels":{"0":{"handshake":8}}}}'],
]);

test('equivalent core configuration is not dirty and keeps its revision', function () {
    config(['node_agent.enabled' => true]);
    $config = json_decode('{"settings":{},"policy":{"levels":{"0":{"handshake":8}}},"routes":[]}', false, 512, JSON_THROW_ON_ERROR);
    $node = Node::factory()->create(['core_config' => $config]);
    $revision = $node->fresh()->desired_revision;
    $node->core_config = json_decode(json_encode($config, JSON_PRETTY_PRINT), false, 512, JSON_THROW_ON_ERROR);

    expect($node->isDirty('core_config'))->toBeFalse();
    $node->save();
    expect($node->fresh()->desired_revision)->toBe($revision);
});
