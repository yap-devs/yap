<?php

use App\Jobs\GenerateClashProfileLink;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Bus;

test('it credits cashback for paid top-ups on the target date', function () {
    Bus::fake();

    $user = User::factory()->create(['balance' => 5]);
    $other_user = User::factory()->create(['balance' => 1]);

    $user->payments()->create([
        'gateway' => Payment::GATEWAY_ALIPAY,
        'status' => Payment::STATUS_PAID,
        'amount' => 10.50,
        'remote_id' => 'A100',
        'created_at' => '2026-06-03 12:00:00',
        'updated_at' => '2026-06-03 12:00:00',
    ]);
    $user->payments()->create([
        'gateway' => Payment::GATEWAY_USDT,
        'status' => Payment::STATUS_PAID,
        'amount' => 2.25,
        'remote_id' => 'U100',
        'created_at' => '2026-06-03 23:59:59',
        'updated_at' => '2026-06-03 23:59:59',
    ]);
    $user->payments()->create([
        'gateway' => Payment::GATEWAY_STRIPE,
        'status' => Payment::STATUS_CREATED,
        'amount' => 99,
        'remote_id' => 'S100',
        'created_at' => '2026-06-03 13:00:00',
        'updated_at' => '2026-06-03 13:00:00',
    ]);
    $user->payments()->create([
        'gateway' => Payment::GATEWAY_ALIPAY,
        'status' => Payment::STATUS_PAID,
        'amount' => 8,
        'remote_id' => 'A101',
        'created_at' => '2026-06-02 23:59:59',
        'updated_at' => '2026-06-02 23:59:59',
    ]);
    $other_user->payments()->create([
        'gateway' => Payment::GATEWAY_GITHUB,
        'status' => Payment::STATUS_PAID,
        'amount' => 3,
        'remote_id' => 'G100',
        'created_at' => '2026-06-03 00:00:00',
        'updated_at' => '2026-06-03 00:00:00',
    ]);

    $this->artisan('app:credit-top-up-cashback-command', [
        'date' => '2026-06-03',
        '--execute' => true,
    ])
        ->assertSuccessful();

    expect((float) $user->refresh()->balance)->toBe(17.75)
        ->and((float) $other_user->refresh()->balance)->toBe(4.0);

    $this->assertDatabaseHas('balance_details', [
        'user_id' => $user->id,
        'amount' => 12.75,
        'description' => 'Top-up cashback for 2026-06-03',
    ]);
    $this->assertDatabaseHas('balance_details', [
        'user_id' => $other_user->id,
        'amount' => 3,
        'description' => 'Top-up cashback for 2026-06-03',
    ]);
    Bus::assertDispatched(GenerateClashProfileLink::class);
});

test('it does not credit twice for the same date', function () {
    $user = User::factory()->create(['balance' => 0]);
    $user->payments()->create([
        'gateway' => Payment::GATEWAY_ALIPAY,
        'status' => Payment::STATUS_PAID,
        'amount' => 2,
        'remote_id' => 'A100',
        'created_at' => '2026-06-03 08:00:00',
        'updated_at' => '2026-06-03 08:00:00',
    ]);

    $this->artisan('app:credit-top-up-cashback-command', [
        'date' => '2026-06-03',
        '--execute' => true,
    ])->assertSuccessful();
    $this->artisan('app:credit-top-up-cashback-command', [
        'date' => '2026-06-03',
        '--execute' => true,
    ])->assertSuccessful();

    expect((float) $user->refresh()->balance)->toBe(2.0)
        ->and($user->balanceDetails()->where('description', 'Top-up cashback for 2026-06-03')->count())->toBe(1);
});

test('it applies a custom top-up cashback ratio', function () {
    $user = User::factory()->create(['balance' => 0]);
    $user->payments()->create([
        'gateway' => Payment::GATEWAY_ALIPAY,
        'status' => Payment::STATUS_PAID,
        'amount' => 2,
        'remote_id' => 'A100',
        'created_at' => '2026-06-03 08:00:00',
        'updated_at' => '2026-06-03 08:00:00',
    ]);

    $this->artisan('app:credit-top-up-cashback-command', [
        'date' => '2026-06-03',
        '--ratio' => '0.5',
        '--execute' => true,
    ])->assertSuccessful();

    expect((float) $user->refresh()->balance)->toBe(1.0);
});

test('it supports a custom balance detail description', function () {
    $user = User::factory()->create(['balance' => 0]);
    $user->payments()->create([
        'gateway' => Payment::GATEWAY_ALIPAY,
        'status' => Payment::STATUS_PAID,
        'amount' => 2,
        'remote_id' => 'A100',
        'created_at' => '2026-06-03 08:00:00',
        'updated_at' => '2026-06-03 08:00:00',
    ]);

    $this->artisan('app:credit-top-up-cashback-command', [
        'date' => '2026-06-03',
        '--description' => 'Dragon Boat top-up bonus {date}',
        '--execute' => true,
    ])->assertSuccessful();

    $this->assertDatabaseHas('balance_details', [
        'user_id' => $user->id,
        'amount' => 2,
        'description' => 'Dragon Boat top-up bonus 2026-06-03',
    ]);
});

test('it previews top-up cashback without crediting by default', function () {
    Bus::fake();

    $user = User::factory()->create(['balance' => 0]);
    $user->payments()->create([
        'gateway' => Payment::GATEWAY_ALIPAY,
        'status' => Payment::STATUS_PAID,
        'amount' => 2,
        'remote_id' => 'A100',
        'created_at' => '2026-06-03 08:00:00',
        'updated_at' => '2026-06-03 08:00:00',
    ]);

    $this->artisan('app:credit-top-up-cashback-command', ['date' => '2026-06-03'])
        ->expectsOutput('Would credit 1 users with 2.00 top-up cashback for 2026-06-03.')
        ->assertSuccessful();

    expect((float) $user->refresh()->balance)->toBe(0.0);
    $this->assertDatabaseMissing('balance_details', [
        'user_id' => $user->id,
        'amount' => 2,
        'description' => 'Top-up cashback for 2026-06-03',
    ]);
    Bus::assertNotDispatched(GenerateClashProfileLink::class);
});
