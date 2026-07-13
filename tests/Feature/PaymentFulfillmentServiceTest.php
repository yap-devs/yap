<?php

use App\Jobs\GenerateClashProfileLink;
use App\Jobs\SyncSub2apiUser;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\PaymentFulfillmentService;
use App\Services\Sub2apiKeyService;
use App\Services\Sub2apiService;
use Illuminate\Support\Facades\Bus;

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

    Bus::assertNotDispatched(GenerateClashProfileLink::class);
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

    Bus::assertDispatched(GenerateClashProfileLink::class);
    Bus::assertDispatched(SyncSub2apiUser::class, fn (SyncSub2apiUser $job): bool => $job->user_id === $user->id);
});
