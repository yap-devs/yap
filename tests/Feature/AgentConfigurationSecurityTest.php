<?php

use App\Models\Node;
use App\Services\NodeConfigurationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

test('revoked authenticated requests cannot obtain cached or rebuilt node credentials', function (bool $cached, bool $disabled) {
    Notification::fake();
    config(['node_agent.enabled' => true, 'node_agent.snapshot_store' => 'array']);
    $token = Str::random(64);
    $node = Node::factory()->create(['agent_token_hash' => hash('sha256', $token)]);
    if ($cached) {
        (new NodeConfigurationService)->snapshot($node);
    }
    $service = Mockery::mock(NodeConfigurationService::class);
    $service->shouldReceive('snapshot')->once()->andReturnUsing(function (Node $authenticated_node) use ($node, $disabled): array {
        if ($disabled) {
            $node->update(['enabled' => false]);
        } else {
            $node->update(['agent_token_hash' => hash('sha256', Str::random(64))]);
        }

        return (new NodeConfigurationService)->snapshot($authenticated_node);
    });
    app()->instance(NodeConfigurationService::class, $service);
    $this->withToken($token)->getJson('/api/agent/v1/config')->assertUnauthorized();
    if (! $cached) {
        expect(Cache::store('array')->has('node-snapshot:'.$node->id))->toBeFalse();
    }
})->with([[false, false], [true, false], [false, true], [true, true]]);
