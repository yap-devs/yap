<?php

use App\Models\Payment;
use App\Models\User;
use Yansongda\LaravelPay\Facades\Pay;

test('alipay scan query returns local payment status without querying alipay', function () {
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
