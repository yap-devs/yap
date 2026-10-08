<?php

use App\Models\Node;
use App\Models\NodeRoute;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('new destinations start disabled without legacy ownership metadata', function () {
    $node = Node::create(['name' => 'New', 'enabled' => false]);
    expect($node->enabled)->toBeFalse()->and($node->getAttributes())->not->toHaveKeys(['traffic_source', 'legacy_internal_server']);
});

test('soft deleted destinations stay excluded from subscriptions', function () {
    $node = Node::factory()->create();
    NodeRoute::factory()->for($node)->create();
    $node->delete();
    $user = User::factory()->create(['balance' => 10, 'uuid' => (string) Str::uuid()]);
    expect(app(SubscriptionService::class)->serversFor($user))->toHaveCount(0);
});

test('node schema has no legacy ownership fields', function () {
    expect(Schema::getColumnListing('nodes'))->not->toContain('traffic_source', 'legacy_internal_server');
});

test('runtime has no legacy table dependency after retirement', function () {
    expect(Schema::hasTable('vmess_servers'))->toBeFalse()->and(Schema::hasTable('relay_servers'))->toBeFalse();
});

test('legacy cutover commands are no longer available', function () {
    expect(array_keys(Artisan::all()))->not->toContain('node:cutover', 'node:import-manifest');
});
