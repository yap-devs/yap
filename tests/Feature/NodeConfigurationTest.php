<?php

use App\Models\Node;
use App\Models\NodeRoute;
use App\Models\Package;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\NodeConfigurationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['node_agent.enabled' => true, 'node_agent.snapshot_store' => 'array']);
    Notification::fake();
});

test('node snapshots include only eligible users and owned routes', function () {
    $token = Str::random(64);
    $node = Node::factory()->create(['agent_token_hash' => hash('sha256', $token)]);
    $route = NodeRoute::factory()->for($node)->create();
    NodeRoute::factory()->create();
    $valid = User::factory()->create(['balance' => 1, 'github_created_at' => null]);
    User::factory()->create(['balance' => 0, 'github_created_at' => null]);
    $response = $this->withToken($token)->getJson('/api/agent/v1/config')->assertOk();
    $response->assertJsonPath('routes.0.id', $route->id)->assertJsonCount(1, 'routes')
        ->assertJsonCount(1, 'users')->assertJsonPath('users.0.id', $valid->id)
        ->assertJsonPath('users.0.email', 'user-'.$valid->id);
});

test('recharge and uuid changes invalidate node authorization', function () {
    $node = Node::factory()->create();
    $user = User::factory()->create(['balance' => 0, 'github_created_at' => null]);
    $revision = $node->fresh()->desired_revision;
    $user->update(['balance' => 10]);
    expect($node->fresh()->desired_revision)->toBeGreaterThan($revision);
    $revision = $node->fresh()->desired_revision;
    $user->update(['balance' => 11]);
    expect($node->fresh()->desired_revision)->toBe($revision);
    $user->update(['uuid' => (string) Str::uuid()]);
    expect($node->fresh()->desired_revision)->toBeGreaterThan($revision);
});

test('expired packages cannot authorize users before cron updates their status', function () {
    $node = Node::factory()->create();
    $user = User::factory()->create(['balance' => 0, 'github_created_at' => null]);
    UserPackage::create(['package_id' => Package::create(['name' => 'Test', 'price' => 1, 'traffic_limit' => 1024])->id, 'remaining_traffic' => 1024, 'priority' => 0, 'user_id' => $user->id, 'status' => UserPackage::STATUS_ACTIVE,
        'started_at' => now()->subDays(2), 'ended_at' => now()->subMinute()]);
    expect($user->fresh()->is_valid)->toBeFalse()
        ->and(app(NodeConfigurationService::class)->snapshot($node->fresh())['users'])->toBe([]);
});

test('time expiry and cache eviction cannot return an unchanged stale snapshot', function () {
    $node = Node::factory()->create();
    $user = User::factory()->create(['balance' => 0, 'github_created_at' => null]);
    UserPackage::create(['package_id' => Package::create(['name' => 'Test', 'price' => 1, 'traffic_limit' => 1024])->id,
        'user_id' => $user->id, 'remaining_traffic' => 1024, 'status' => UserPackage::STATUS_ACTIVE,
        'started_at' => now()->subMinute(), 'ended_at' => now()->addSeconds(10)]);
    $service = app(NodeConfigurationService::class);
    $first = $service->snapshot($node->fresh());
    expect($first['users'])->toHaveCount(1);
    $this->travel(11)->seconds();
    $expired = $service->snapshot($node->fresh());
    expect($expired['users'])->toBe([])->and($expired['revision'])->toBeGreaterThan($first['revision']);
    Cache::store('array')->flush();
    $rebuilt = $service->snapshot($node->fresh());
    expect($rebuilt['revision'])->toBeGreaterThan($expired['revision']);
});

test('snapshot cache replaces the fixed node key across revisions', function () {
    $node = Node::factory()->create();
    $service = app(NodeConfigurationService::class);
    $first = $service->snapshot($node->fresh());
    expect(Cache::store('array')->get('node-snapshot:'.$node->id)['revision'])->toBe($first['revision']);
    $node->increment('desired_revision');
    $second = $service->snapshot($node->fresh());
    expect($second['revision'])->toBeGreaterThan($first['revision'])
        ->and(Cache::store('array')->get('node-snapshot:'.$node->id)['revision'])->toBe($second['revision'])
        ->and(Cache::store('array')->has('node-snapshot:'.$node->id.':'.$first['revision']))->toBeFalse();
});

test('multiplier and public endpoint edits do not invalidate core authorization', function () {
    $node = Node::factory()->create();
    $route = NodeRoute::factory()->for($node)->create();
    $revision = $node->fresh()->desired_revision;
    $route->update(['rate' => '3.00', 'server' => 'new-relay.example.com', 'port' => 8443, 'name' => 'New relay']);
    expect($node->fresh()->desired_revision)->toBe($revision);
    $route->update(['enabled' => false]);
    expect($node->fresh()->desired_revision)->toBeGreaterThan($revision);
});

test('node heartbeat stores reported versions at most once per minute', function () {
    $token = Str::random(64);
    $node = Node::factory()->create(['agent_token_hash' => hash('sha256', $token)]);
    $this->withToken($token)->getJson('/api/agent/v1/config?agent_version=0.1.0&core_version=v5.16.1-yap')->assertOk();
    expect($node->fresh()->agent_version)->toBe('0.1.0')->and($node->fresh()->core_version)->toBe('v5.16.1-yap');
    $seen = $node->fresh()->last_seen_at;
    $this->travel(10)->seconds();
    $this->withToken($token)->getJson('/api/agent/v1/config?agent_version=0.2.0')->assertOk();
    expect($node->fresh()->last_seen_at->equalTo($seen))->toBeTrue()->and($node->fresh()->agent_version)->toBe('0.1.0');
});

test('node configuration preserves json objects numeric keys and arrays', function () {
    $token = Str::random(64);
    $core_config = json_decode('{"policy":{"levels":{"0":{"bufferSize":4,"handshake":8}}},"outbounds":[{"protocol":"blackhole","settings":{}}],"routing":{"rules":[]}}', false, 512, JSON_THROW_ON_ERROR);
    $node = Node::factory()->create(['agent_token_hash' => hash('sha256', $token), 'core_config' => $core_config]);

    $response = $this->withToken($token)->getJson('/api/agent/v1/config')->assertOk();
    $snapshot = json_decode($response->getContent(), false, 512, JSON_THROW_ON_ERROR);

    expect($node->fresh()->core_config)->toBeInstanceOf(stdClass::class)
        ->and($snapshot->core_config->policy->levels)->toBeInstanceOf(stdClass::class)
        ->and($snapshot->core_config->policy->levels->{'0'}->bufferSize)->toBe(4)
        ->and($snapshot->core_config->policy->levels->{'0'}->handshake)->toBe(8)
        ->and($snapshot->core_config->outbounds)->toBeArray()
        ->and($snapshot->core_config->outbounds[0]->settings)->toBeInstanceOf(stdClass::class)
        ->and($snapshot->core_config->routing->rules)->toBe([]);
});

test('node configuration without a base config returns an empty json object', function () {
    $node = Node::factory()->create();
    $snapshot = app(NodeConfigurationService::class)->snapshot($node);

    expect($snapshot['core_config'])->toBeInstanceOf(stdClass::class)
        ->and(json_encode($snapshot['core_config']))->toBe('{}');
});
