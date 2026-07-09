<?php

use App\Models\Payment;
use App\Models\User;
use App\Services\AlipayPaymentStatusService;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Yansongda\Pay\Provider\Alipay;
use Yansongda\Supports\Collection;

function createAlipayPayment(string $status = Payment::STATUS_CREATED): Payment
{
    $user = User::factory()->create();

    return $user->payments()->create([
        'gateway' => Payment::GATEWAY_ALIPAY,
        'status' => $status,
        'amount' => 5,
        'remote_id' => 'A'.random_int(100000, 999999),
        'payload' => [
            Payment::STATUS_CREATED => [],
        ],
    ]);
}

test('snapshot returns empty trade status and defers refresh when cache is missing', function () {
    $payment = createAlipayPayment();
    $service = app(AlipayPaymentStatusService::class);

    $alipay = Mockery::mock(Alipay::class);
    $alipay->shouldReceive('query')->never();
    app()->instance('pay.alipay', $alipay);

    $snapshot = $service->snapshot($payment);

    expect($snapshot)->toMatchArray([
        'payment_status' => Payment::STATUS_CREATED,
        'trade_status' => null,
    ])
        ->and(Cache::get('alipay:trade-status:'.$payment->id))->toMatchArray([
            'trade_status' => null,
        ])
        ->and(app(DeferredCallbackCollection::class))->toHaveCount(1);
});

test('snapshot returns cached alipay trade status without querying alipay', function () {
    $payment = createAlipayPayment();
    Cache::put('alipay:trade-status:'.$payment->id, [
        'trade_status' => 'WAIT_BUYER_PAY',
        'payload' => ['trade_status' => 'WAIT_BUYER_PAY'],
    ], now()->addSeconds(5));

    $alipay = Mockery::mock(Alipay::class);
    $alipay->shouldReceive('query')->never();
    app()->instance('pay.alipay', $alipay);

    $snapshot = app(AlipayPaymentStatusService::class)->snapshot($payment);

    expect($snapshot)->toMatchArray([
        'payment_status' => Payment::STATUS_CREATED,
        'trade_status' => 'WAIT_BUYER_PAY',
    ]);
});

test('snapshot returns empty trade status without deferring refresh while placeholder exists', function () {
    $payment = createAlipayPayment();
    Cache::put('alipay:trade-status:'.$payment->id, [
        'trade_status' => null,
    ], now()->addSeconds(5));

    $alipay = Mockery::mock(Alipay::class);
    $alipay->shouldReceive('query')->never();
    app()->instance('pay.alipay', $alipay);

    $snapshot = app(AlipayPaymentStatusService::class)->snapshot($payment);

    expect($snapshot)->toMatchArray([
        'payment_status' => Payment::STATUS_CREATED,
        'trade_status' => null,
    ])
        ->and(app(DeferredCallbackCollection::class))->toHaveCount(0);
});

test('refresh fulfills payment when alipay reports trade success', function () {
    Bus::fake();
    $payment = createAlipayPayment();
    $user = $payment->user;

    $alipay = Mockery::mock(Alipay::class);
    $alipay->shouldReceive('query')
        ->once()
        ->with(['out_trade_no' => $payment->remote_id])
        ->andReturn(new Collection([
            'trade_status' => 'TRADE_SUCCESS',
            'out_trade_no' => $payment->remote_id,
            'total_amount' => '5.00',
        ]));
    app()->instance('pay.alipay', $alipay);

    $result = app(AlipayPaymentStatusService::class)->refresh($payment);

    expect($result)->toMatchArray([
        'trade_status' => 'TRADE_SUCCESS',
    ])
        ->and($payment->refresh()->status)->toBe(Payment::STATUS_PAID)
        ->and((float) $user->refresh()->balance)->toBe(5.0)
        ->and(Cache::get('alipay:trade-status:'.$payment->id))->toMatchArray([
            'trade_status' => 'TRADE_SUCCESS',
        ]);
});
