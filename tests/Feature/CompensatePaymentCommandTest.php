<?php

use App\Jobs\GenerateClashProfileLink;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Bus;

test('expired payment can be compensated as paid', function () {
    Bus::fake();

    $user = User::factory()->create(['balance' => 1]);
    $payment = $user->payments()->create([
        'gateway' => Payment::GATEWAY_USDT,
        'status' => Payment::STATUS_EXPIRED,
        'amount' => 12.34,
        'remote_id' => 'U123456',
        'payload' => [
            Payment::STATUS_CREATED => ['order_id' => 'U123456'],
        ],
    ]);

    $this->artisan('payment:compensate', [
        'payment' => $payment->id,
        '--reason' => 'late webhook',
        '--yes' => true,
    ])->assertSuccessful();

    $payment->refresh();
    $user->refresh();

    expect($payment->status)->toBe(Payment::STATUS_PAID)
        ->and((float) $user->balance)->toBe(13.34)
        ->and($payment->payload[Payment::STATUS_PAID]['reason'])->toBe('late webhook')
        ->and($payment->payload[Payment::STATUS_PAID]['previous_status'])->toBe(Payment::STATUS_EXPIRED);

    $this->assertDatabaseHas('balance_details', [
        'user_id' => $user->id,
        'amount' => 12.34,
        'description' => __('messages.balance_descriptions.usdt_payment', [], 'en'),
    ]);

    Bus::assertDispatched(GenerateClashProfileLink::class);
});

test('cancelled payment can be compensated as paid', function () {
    Bus::fake();

    $user = User::factory()->create(['balance' => 0]);
    $payment = $user->payments()->create([
        'gateway' => Payment::GATEWAY_ALIPAY,
        'status' => Payment::STATUS_CANCELLED,
        'amount' => 10,
        'remote_id' => 'A123456',
        'payload' => [],
    ]);

    $this->artisan('payment:compensate', [
        'payment' => $payment->id,
        '--yes' => true,
    ])->assertSuccessful();

    $payment->refresh();
    $user->refresh();

    expect($payment->status)->toBe(Payment::STATUS_PAID)
        ->and((float) $user->balance)->toBe(10.0);

    $this->assertDatabaseHas('balance_details', [
        'user_id' => $user->id,
        'amount' => 10,
        'description' => __('messages.balance_descriptions.alipay_payment', [], 'en'),
    ]);
});

test('created payment cannot be compensated', function () {
    Bus::fake();

    $user = User::factory()->create(['balance' => 1]);
    $payment = $user->payments()->create([
        'gateway' => Payment::GATEWAY_STRIPE,
        'status' => Payment::STATUS_CREATED,
        'amount' => 9,
        'remote_id' => 'S123456',
        'payload' => [],
    ]);

    $this->artisan('payment:compensate', [
        'payment' => $payment->id,
        '--yes' => true,
    ])->assertFailed();

    $payment->refresh();
    $user->refresh();

    expect($payment->status)->toBe(Payment::STATUS_CREATED)
        ->and((float) $user->balance)->toBe(1.0);

    $this->assertDatabaseMissing('balance_details', [
        'user_id' => $user->id,
        'amount' => 9,
    ]);
    Bus::assertNotDispatched(GenerateClashProfileLink::class);
});
