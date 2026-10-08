<?php

use App\Models\Node;
use App\Models\NodeRoute;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

test('subscriptions are generated per user without a global synchronization job', function () {
    $user = User::factory()->create(['balance' => 1, 'uuid' => (string) Str::uuid()]);
    NodeRoute::factory()->create(['name' => 'Live']);
    expect(app(SubscriptionService::class)->content($user, 'clash'))->toContain('Live[1x]');
    expect(class_exists('App\\Jobs\\GenerateClashProfileLink'))->toBeFalse();
});

test('subscription cache rebuild includes all authorized enabled routes', function () {
    $user = User::factory()->create(['balance' => 1, 'uuid' => (string) Str::uuid()]);
    NodeRoute::factory()->create(['name' => 'One']);
    NodeRoute::factory()->create(['name' => 'Two']);
    $this->artisan('app:gen-sub-link-command')->assertSuccessful();
    expect(app(SubscriptionService::class)->content($user, 'clash'))->toContain('One[1x]', 'Two[1x]');
});

test('cache generation no longer requires a queued unique synchronization lock', function () {
    $user = User::factory()->create(['balance' => 1, 'uuid' => (string) Str::uuid()]);
    NodeRoute::factory()->create();
    $lock = Cache::lock('generate-clash-profile-link:processing', 330);
    $lock->get();
    try {
        $this->artisan('app:gen-sub-link-command')->assertSuccessful();
    } finally {
        $lock->release();
    }
});

test('disabled nodes are excluded even when routes remain enabled', function () {
    $user = User::factory()->create(['balance' => 1, 'uuid' => (string) Str::uuid()]);
    NodeRoute::factory()->for(Node::factory()->create(['enabled' => false]))->create(['name' => 'Hidden']);
    expect(app(SubscriptionService::class)->content($user, 'clash'))->not->toContain('Hidden[1x]');
});
