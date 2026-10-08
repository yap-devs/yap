<?php

use App\Models\Node;
use App\Models\NodeRoute;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

beforeEach(function () {
    config(['node_agent.subscriptions_enabled' => true, 'subscription.content_store' => 'array', 'subscription.lock_store' => 'array']);
    Bus::fake();
});

test('node subscriptions build only the requested format on the first request', function () {
    $user = User::factory()->create(['uuid' => (string) Str::uuid(), 'balance' => 1]);
    NodeRoute::factory()->create(['name' => 'Tokyo', 'rate' => '1.25', 'server' => 'entry.example.com']);
    NodeRoute::factory()->create(['enabled' => false]);
    NodeRoute::factory()->for(Node::factory()->state(['enabled' => false]))->create();

    $response = $this->get(route('subscription.clash', ['uuid' => $user->uuid]))->assertOk();
    $config = Yaml::parse($response->getContent());

    expect($config['proxies'])->toHaveCount(1)
        ->and($config['proxies'][0]['name'])->toBe('Tokyo[1.25x]')
        ->and($config['proxies'][0]['server'])->toBe('entry.example.com')
        ->and(Cache::has(app(SubscriptionService::class)->cacheKey($user, 'universal')))->toBeFalse();
    Bus::assertNotDispatched('App\\Jobs\\GenerateClashProfileLink');
});

test('node route and credential changes replace the current cached content', function () {
    $user = User::factory()->create(['uuid' => (string) Str::uuid(), 'balance' => 1]);
    $route = NodeRoute::factory()->create(['server' => 'old.example.com']);
    $service = app(SubscriptionService::class);
    $key = $service->cacheKey($user, 'universal');
    $first = $service->content($user, 'universal');
    $first_entry = Cache::get($key);
    $route->update(['server' => 'new.example.com']);
    $second = $service->content($user, 'universal');

    expect($second)->not->toBe($first)
        ->and(Cache::get($key)['signature'])->not->toBe($first_entry['signature']);
    $user->uuid = 'new-uuid';
    $third = $service->content($user, 'universal');
    $payload = json_decode(base64_decode(substr(trim(base64_decode($third)), 8)), true);
    expect($payload['id'])->toBe('new-uuid')->and($payload['add'])->toBe('new.example.com');
    $route->node->update(['enabled' => false]);
    expect(trim(base64_decode($service->content($user, 'universal'))))->toBe('');
});

test('priority changes invalidate subscriptions and remove restricted routes', function () {
    $user = User::factory()->create(['uuid' => (string) Str::uuid(), 'balance' => 1]);
    NodeRoute::factory()->create(['for_low_priority' => false]);
    NodeRoute::factory()->create(['name' => 'Shared', 'for_low_priority' => true]);
    $service = app(SubscriptionService::class);
    expect(Yaml::parse($service->content($user, 'clash'))['proxies'])->toHaveCount(2);
    $user->balance = 0;
    $config = Yaml::parse($service->content($user, 'clash'));
    expect($config['proxies'])->toHaveCount(1)->and($config['proxies'][0]['name'])->toBe('Shared[1x]');
});

test('hot subscription cache is reused and expires after a bounded lifetime', function () {
    config(['subscription.ttl_seconds' => 5]);
    $user = User::factory()->create(['uuid' => (string) Str::uuid(), 'balance' => 1]);
    NodeRoute::factory()->create();
    $service = app(SubscriptionService::class);
    $service->content($user, 'clash');
    $key = $service->cacheKey($user, 'clash');
    $entry = Cache::get($key);
    $entry['content'] = 'cached result';
    Cache::put($key, $entry, 5);
    expect($service->content($user, 'clash'))->toBe('cached result');
    $this->travel(6)->seconds();
    expect($service->content($user, 'clash'))->not->toBe('cached result');
});

test('invalid users cannot retrieve a previously cached node subscription', function () {
    $user = User::factory()->create(['uuid' => (string) Str::uuid(), 'balance' => 1, 'github_created_at' => null]);
    NodeRoute::factory()->create();
    app(SubscriptionService::class)->warmCache($user);
    $user->update(['balance' => 0]);
    $this->get(route('subscription.clash', ['uuid' => $user->uuid]))->assertNotFound();
});

test('busy subscription generation is bounded and independent per user and format', function () {
    config(['subscription.lock_wait_seconds' => 0]);
    $user = User::factory()->create(['uuid' => (string) Str::uuid(), 'balance' => 1]);
    $other_user = User::factory()->create(['uuid' => (string) Str::uuid(), 'balance' => 1]);
    NodeRoute::factory()->create();
    $service = app(SubscriptionService::class);
    $lock = Cache::lock($service->cacheKey($user, 'clash').':lock', 60);
    expect($lock->get())->toBeTrue();

    try {
        $this->get(route('subscription.clash', ['uuid' => $user->uuid]))->assertServiceUnavailable();
        expect(Cache::has($service->cacheKey($user, 'clash')))->toBeFalse();
        $this->get(route('subscription.universal', ['uuid' => $user->uuid]))->assertOk();
        $this->get(route('subscription.clash', ['uuid' => $other_user->uuid]))->assertOk();
    } finally {
        $lock->release();
    }

    $this->get(route('subscription.clash', ['uuid' => $user->uuid]))->assertOk();
});

test('subscriptions include only enabled agent routes after legacy retirement', function () {
    $user = User::factory()->create(['uuid' => (string) Str::uuid(), 'balance' => 1]);
    $node = Node::factory()->create();
    NodeRoute::factory()->for($node)->create(['name' => 'Live', 'server' => 'live.public']);
    $disabled = Node::factory()->create(['enabled' => false]);
    NodeRoute::factory()->for($disabled)->create(['name' => 'Not Yet Live']);
    $config = Yaml::parse(app(SubscriptionService::class)->content($user, 'clash'));
    expect(array_column($config['proxies'], 'server'))->toBe(['live.public']);
});
