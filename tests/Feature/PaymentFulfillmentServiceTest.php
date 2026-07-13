<?php

use App\Jobs\SyncSub2apiUser;
use App\Models\Payment;
use App\Models\User;
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
