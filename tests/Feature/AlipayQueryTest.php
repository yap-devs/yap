<?php

use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Yansongda\LaravelPay\Facades\Pay;
use Yansongda\Pay\Provider\Alipay;
use Yansongda\Supports\Collection;

test('alipay scan query returns created payment without claiming buyer is waiting to pay', function () {
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
            'trade_status' => 'WAIT_BUYER_PAY',
            'out_trade_no' => $payment->remote_id,
        ]));
    app()->instance('pay.alipay', $alipay);

    $response = $this
        ->actingAs($user)
        ->getJson(route('alipay.query', ['payment' => $payment]));

    $response
        ->assertSuccessful()
        ->assertJson([
            'payment_status' => Payment::STATUS_CREATED,
            'trade_status' => null,
        ]);

    expect(Cache::get('alipay:trade-status:'.$payment->id))->toMatchArray([
        'trade_status' => 'WAIT_BUYER_PAY',
    ]);
});

test('alipay scan query maps paid payment to trade success', function () {
    $user = User::factory()->create();

    $payment = $user->payments()->create([
        'gateway' => Payment::GATEWAY_ALIPAY,
        'status' => Payment::STATUS_PAID,
        'amount' => 5,
        'remote_id' => 'A123457',
        'payload' => [
            Payment::STATUS_CREATED => [],
        ],
    ]);

    Pay::shouldReceive('alipay')->never();

    $response = $this
        ->actingAs($user)
        ->getJson(route('alipay.query', ['payment' => $payment]));

    $response
        ->assertSuccessful()
        ->assertJson([
            'payment_status' => Payment::STATUS_PAID,
            'trade_status' => 'TRADE_SUCCESS',
        ]);
});

test('alipay scan query returns cached waiting buyer pay status', function () {
    $user = User::factory()->create();

    $payment = $user->payments()->create([
        'gateway' => Payment::GATEWAY_ALIPAY,
        'status' => Payment::STATUS_CREATED,
        'amount' => 5,
        'remote_id' => 'A123458',
        'payload' => [
            Payment::STATUS_CREATED => [],
        ],
    ]);

    Cache::put('alipay:trade-status:'.$payment->id, [
        'trade_status' => 'WAIT_BUYER_PAY',
    ], now()->addSeconds(5));

    Pay::shouldReceive('alipay')->never();

    $response = $this
        ->actingAs($user)
        ->getJson(route('alipay.query', ['payment' => $payment]));

    $response
        ->assertSuccessful()
        ->assertJson([
            'payment_status' => Payment::STATUS_CREATED,
            'trade_status' => 'WAIT_BUYER_PAY',
        ]);
});

test('alipay scan query rejects non alipay payments', function () {
    $user = User::factory()->create();

    $payment = $user->payments()->create([
        'gateway' => Payment::GATEWAY_STRIPE,
        'status' => Payment::STATUS_PAID,
        'amount' => 5,
        'remote_id' => 'S123457',
        'payload' => [
            Payment::STATUS_CREATED => [],
        ],
    ]);

    Pay::shouldReceive('alipay')->never();

    $response = $this
        ->actingAs($user)
        ->get(route('alipay.query', ['payment' => $payment]));

    $response
        ->assertRedirect(route('recharge'))
        ->assertSessionHasErrors([
            'message' => __('messages.errors.invalid_payment_gateway'),
        ]);
});
