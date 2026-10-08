<?php

use App\Models\Node;
use App\Models\NodeRoute;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

test('new destinations use agent ownership without a legacy import', function () {
    $node = Node::create(['name' => 'New', 'enabled' => false]);
    expect($node->traffic_source)->toBe('agent');
});

test('soft deleted destinations stay excluded from subscriptions', function () {
    $node = Node::factory()->create();
    NodeRoute::factory()->for($node)->create();
    $node->delete();
    $user = User::factory()->create(['balance' => 10, 'uuid' => (string) Str::uuid()]);
    expect(app(SubscriptionService::class)->serversFor($user))->toHaveCount(0);
});

test('legacy ownership cannot be reintroduced after retirement', function () {
    expect(fn () => Node::factory()->create(['traffic_source' => 'legacy']))->toThrow(ValidationException::class);
});

test('runtime has no legacy table dependency after retirement', function () {
    expect(Schema::hasTable('vmess_servers'))->toBeFalse()->and(Schema::hasTable('relay_servers'))->toBeFalse();
});

test('legacy cutover commands are no longer available', function () {
    expect(array_keys(Artisan::all()))->not->toContain('node:cutover', 'node:import-manifest');
});
