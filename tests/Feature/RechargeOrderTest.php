<?php

use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

test('cannot create a recharge order while another create request holds the lock', function () {
    $user = User::factory()->create();
    $lock = Cache::lock('recharge-order:create:user:'.$user->id, 10);

    expect($lock->get())->toBeTrue();

    $response = $this
        ->actingAs($user)
        ->from(route('recharge'))
        ->post(route('alipay.newOrder'), [
            'amount' => 5,
        ]);

    $response
        ->assertRedirect(route('recharge'))
        ->assertSessionHasErrors([
            'message' => 'A recharge order is already being created. Please wait a moment and try again.',
        ]);

    expect(Payment::count())->toBe(0);

    $lock->release();
});

test('cannot create a recharge order when an unpaid one already exists', function () {
    $user = User::factory()->create();

    $user->payments()->create([
        'gateway' => Payment::GATEWAY_ALIPAY,
        'status' => Payment::STATUS_CREATED,
        'amount' => 5,
        'remote_id' => 'existing-order',
        'payload' => [
            Payment::STATUS_CREATED => [],
        ],
    ]);

    $response = $this
        ->actingAs($user)
        ->from(route('recharge'))
        ->post(route('alipay.newOrder'), [
            'amount' => 5,
        ]);

    $response
        ->assertRedirect(route('recharge'))
        ->assertSessionHasErrors([
            'message' => 'You have an unpaid payment. Please complete or cancel it before creating a new one.',
        ]);

    expect(Payment::count())->toBe(1);
});
