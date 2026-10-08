<?php

use App\Jobs\SyncSub2apiUser;
use App\Models\Node;
use App\Models\NodeRoute;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\NodeConfigurationService;
use App\Services\PaymentFulfillmentService;
use App\Services\Sub2apiKeyService;
use App\Services\Sub2apiService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

test('recharge restores node authorization and subscriptions without duplicate credit', function (int $initial_balance) {
    Bus::fake();
    Notification::fake();
    config([
        'node_agent.enabled' => true,
        'node_agent.subscriptions_enabled' => true,
        'node_agent.snapshot_store' => 'array',
        'subscription.content_store' => 'array',
        'subscription.lock_store' => 'array',
    ]);

    $token = Str::random(64);
    $node = Node::factory()->create(['agent_token_hash' => hash('sha256', $token)]);
    NodeRoute::factory()->for($node)->create(['server' => 'recovered.example.com']);
    $user = User::factory()->create([
        'balance' => $initial_balance,
        'github_created_at' => null,
        'uuid' => (string) Str::uuid(),
    ]);
    $initial_snapshot = app(NodeConfigurationService::class)->snapshot($node->fresh());
    expect(array_column($initial_snapshot['users'], 'id'))->not->toContain($user->id);
    $this->get(route('subscription.clash', ['uuid' => $user->uuid]))->assertNotFound();

    $payment = $user->payments()->create([
        'gateway' => Payment::GATEWAY_ALIPAY,
        'status' => Payment::STATUS_CREATED,
        'amount' => 5,
        'remote_id' => 'sandbox-recharge',
        'payload' => [],
    ]);
    $service = app(PaymentFulfillmentService::class);
    expect($service->fulfill($payment, ['trade_status' => 'TRADE_SUCCESS']))->toBeTrue();

    $snapshot = $this->withToken($token)->getJson('/api/agent/v1/config')->assertOk()->json();
    expect($snapshot['revision'])->toBeGreaterThan($initial_snapshot['revision'])
        ->and(array_column($snapshot['users'], 'id'))->toContain($user->id)
        ->and((float) $user->refresh()->balance)->toBe((float) ($initial_balance + 5))
        ->and($user->balanceDetails()->count())->toBe(1);

    $response = $this->get(route('subscription.clash', ['uuid' => $user->uuid]))->assertOk();
    expect(Yaml::parse($response->getContent())['proxies'][0]['server'])->toBe('recovered.example.com');

    expect($service->fulfill($payment, ['trade_status' => 'TRADE_SUCCESS']))->toBeFalse()
        ->and((float) $user->refresh()->balance)->toBe((float) ($initial_balance + 5))
        ->and($user->balanceDetails()->count())->toBe(1)
        ->and($node->fresh()->desired_revision)->toBe($snapshot['revision']);
    Bus::assertNotDispatched('App\\Jobs\\GenerateClashProfileLink');
    Bus::assertDispatchedTimes(SyncSub2apiUser::class, 1);
})->with(['new unpaid user' => 0, 'existing user in debt' => -1]);

test('paid recharge dispatches sub2api user sync job', function () {
    Bus::fake();

    config()->set('services.sub2api.enabled', true);
    config()->set('services.sub2api.min_balance_to_keep_active', 0);

    $sub2api_service = Mockery::mock(Sub2apiService::class);
    $sub2api_service->shouldReceive('listUsage')
        ->never();
    $sub2api_service->shouldReceive('getKeepActiveThreshold')
        ->never();
    $sub2api_service->shouldReceive('updateKeyStatus')
        ->never();
    app()->instance(Sub2apiService::class, $sub2api_service);

    $user = User::factory()->create(['balance' => 0]);
    $user->forceFill([
        'sub2api_key_id' => 123,
        'sub2api_key_status' => Sub2apiKeyService::STATUS_INACTIVE,
    ])->save();

    $payment = $user->payments()->create([
        'gateway' => Payment::GATEWAY_ALIPAY,
        'status' => Payment::STATUS_CREATED,
        'amount' => 5,
        'remote_id' => 'A123456',
        'payload' => [
            Payment::STATUS_CREATED => [],
        ],
    ]);

    $fulfilled = app(PaymentFulfillmentService::class)->fulfill($payment, [
        'trade_status' => 'TRADE_SUCCESS',
    ]);

    expect($fulfilled)->toBeTrue()
        ->and($user->refresh()->sub2api_key_status)->toBe(Sub2apiKeyService::STATUS_INACTIVE);

    Bus::assertDispatched(SyncSub2apiUser::class, fn (SyncSub2apiUser $job): bool => $job->user_id === $user->id);
});

test('paid recharge skips clash profile sync when user service status is unchanged', function () {
    Bus::fake();

    $user = User::factory()->create(['balance' => 1]);
    $payment = $user->payments()->create([
        'gateway' => Payment::GATEWAY_ALIPAY,
        'status' => Payment::STATUS_CREATED,
        'amount' => 5,
        'remote_id' => 'A123456',
        'payload' => [
            Payment::STATUS_CREATED => [],
        ],
    ]);

    $fulfilled = app(PaymentFulfillmentService::class)->fulfill($payment, [
        'trade_status' => 'TRADE_SUCCESS',
    ]);

    expect($fulfilled)->toBeTrue()
        ->and((float) $user->refresh()->balance)->toBe(6.0);

    Bus::assertNotDispatched('App\\Jobs\\GenerateClashProfileLink');
    Bus::assertDispatched(SyncSub2apiUser::class, fn (SyncSub2apiUser $job): bool => $job->user_id === $user->id);
});

test('paid recharge dispatches clash profile sync when low priority status changes', function () {
    Bus::fake();

    $user = User::factory()->create(['balance' => 0]);
    $package = Package::create([
        'name' => 'Test Package',
        'price' => 10,
        'traffic_limit' => 10 * 1024 * 1024 * 1024,
        'duration_days' => 30,
        'status' => Package::STATUS_ACTIVE,
    ]);
    $user->packages()->create([
        'package_id' => $package->id,
        'remaining_traffic' => 10 * 1024 * 1024 * 1024,
        'status' => UserPackage::STATUS_ACTIVE,
        'started_at' => now()->subDay(),
        'ended_at' => now()->addMonth(),
    ]);
    $payment = $user->payments()->create([
        'gateway' => Payment::GATEWAY_ALIPAY,
        'status' => Payment::STATUS_CREATED,
        'amount' => 5,
        'remote_id' => 'A123456',
        'payload' => [
            Payment::STATUS_CREATED => [],
        ],
    ]);

    $fulfilled = app(PaymentFulfillmentService::class)->fulfill($payment, [
        'trade_status' => 'TRADE_SUCCESS',
    ]);

    expect($fulfilled)->toBeTrue()
        ->and($user->refresh()->is_low_priority)->toBeFalse();

    Bus::assertNotDispatched('App\\Jobs\\GenerateClashProfileLink');
    Bus::assertDispatched(SyncSub2apiUser::class, fn (SyncSub2apiUser $job): bool => $job->user_id === $user->id);
});
