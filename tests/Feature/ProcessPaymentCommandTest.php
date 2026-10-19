<?php

use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Yansongda\Pay\Provider\Alipay;
use Yansongda\Supports\Collection;

test('process payment command fulfills successful alipay payments through shared status refresh', function () {
    Bus::fake();

    $user = User::factory()->create();
    $payment = $user->payments()->create([
        'gateway' => Payment::GATEWAY_ALIPAY,
        'status' => Payment::STATUS_CREATED,
        'amount' => 5,
        'remote_id' => 'A123456',
        'payload' => [
            Payment::STATUS_CREATED => [],
        ],
    ]);

    $alipay = Mockery::mock(Alipay::class);
    $alipay->shouldReceive('query')
        ->once()
        ->with(['out_trade_no' => $payment->remote_id])
        ->andReturn(new Collection([
            'trade_status' => 'TRADE_SUCCESS',
            'out_trade_no' => $payment->remote_id,
        ]));
    app()->instance('pay.alipay', $alipay);

    $this->artisan('app:process-payment-command')->assertSuccessful();

    expect($payment->refresh()->status)->toBe(Payment::STATUS_PAID)
        ->and((float) $user->refresh()->balance)->toBe(5.0)
        ->and(Cache::get('alipay:trade-status:'.$payment->id))->toMatchArray([
            'trade_status' => 'TRADE_SUCCESS',
        ]);

    Bus::assertNotDispatched('App\\Jobs\\GenerateClashProfileLink');
});

test('payment processing reports an alipay failure and continues with subsequent payments', function () {
    Exceptions::fake();
    Bus::fake();
    $this->freezeTime();
    $user = User::factory()->create();
    $failed_payment = $user->payments()->create([
        'gateway' => Payment::GATEWAY_ALIPAY,
        'status' => Payment::STATUS_CREATED,
        'amount' => 5,
        'remote_id' => 'FAILED',
        'payload' => [Payment::STATUS_CREATED => []],
    ]);
    $successful_payment = $user->payments()->create([
        'gateway' => Payment::GATEWAY_ALIPAY,
        'status' => Payment::STATUS_CREATED,
        'amount' => 7,
        'remote_id' => 'SUCCESSFUL',
        'payload' => [Payment::STATUS_CREATED => []],
    ]);
    $expired_payment = $user->payments()->create([
        'gateway' => Payment::GATEWAY_USDT,
        'status' => Payment::STATUS_CREATED,
        'amount' => 3,
        'remote_id' => 'EXPIRED',
        'created_at' => now()->subMinutes(21),
        'payload' => [Payment::STATUS_CREATED => []],
    ]);
    $exception = new RuntimeException('Alipay connection timed out');
    $alipay = Mockery::mock(Alipay::class);
    $alipay->shouldReceive('query')->once()->with(['out_trade_no' => 'FAILED'])->andThrow($exception);
    $alipay->shouldReceive('query')->once()->with(['out_trade_no' => 'SUCCESSFUL'])
        ->andReturn(new Collection(['trade_status' => 'TRADE_SUCCESS']));
    app()->instance('pay.alipay', $alipay);

    $this->artisan('app:process-payment-command')->assertFailed();

    Exceptions::assertReported(fn (RuntimeException $reported) => $reported === $exception);
    expect($failed_payment->refresh()->status)->toBe(Payment::STATUS_CREATED);
    expect($successful_payment->refresh()->status)->toBe(Payment::STATUS_PAID);
    expect($expired_payment->refresh()->status)->toBe(Payment::STATUS_EXPIRED);
    expect((float) $user->refresh()->balance)->toBe(7.0);
    Bus::assertNotDispatched('App\\Jobs\\GenerateClashProfileLink');
});
