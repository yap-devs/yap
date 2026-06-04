<?php

use App\Jobs\GenerateClashProfileLink;
use App\Models\User;
use Illuminate\Support\Facades\Bus;

test('it credits cashback for billing balance consumed on the target date', function () {
    Bus::fake();

    $user = User::factory()->create(['balance' => 5]);
    $other_user = User::factory()->create(['balance' => 1]);

    $user->balanceDetails()->create([
        'amount' => -2.25,
        'description' => 'Traffic deduction',
        'created_at' => '2026-06-03 12:00:00',
        'updated_at' => '2026-06-03 12:00:00',
    ]);
    $user->balanceDetails()->create([
        'amount' => -1.50,
        'description' => 'Bought package Basic',
        'created_at' => '2026-06-03 23:59:59',
        'updated_at' => '2026-06-03 23:59:59',
    ]);
    $user->balanceDetails()->create([
        'amount' => 10,
        'description' => 'Payment recharge',
        'created_at' => '2026-06-03 13:00:00',
        'updated_at' => '2026-06-03 13:00:00',
    ]);
    $user->balanceDetails()->create([
        'amount' => -9,
        'description' => 'Traffic deduction',
        'created_at' => '2026-06-02 23:59:59',
        'updated_at' => '2026-06-02 23:59:59',
    ]);
    $other_user->balanceDetails()->create([
        'amount' => -0.99,
        'description' => 'Daily deduction',
        'created_at' => '2026-06-03 00:00:00',
        'updated_at' => '2026-06-03 00:00:00',
    ]);

    $this->artisan('app:credit-activity-cashback-command', [
        'date' => '2026-06-03',
        '--execute' => true,
    ])
        ->assertSuccessful();

    expect((float) $user->refresh()->balance)->toBe(7.25)
        ->and((float) $other_user->refresh()->balance)->toBe(1.99);

    $this->assertDatabaseHas('balance_details', [
        'user_id' => $user->id,
        'amount' => 2.25,
        'description' => 'Activity cashback for 2026-06-03',
    ]);
    $this->assertDatabaseHas('balance_details', [
        'user_id' => $other_user->id,
        'amount' => 0.99,
        'description' => 'Activity cashback for 2026-06-03',
    ]);
    Bus::assertDispatched(GenerateClashProfileLink::class);
});

test('it does not credit twice for the same date', function () {
    $user = User::factory()->create(['balance' => 0]);
    $balance_detail = $user->balanceDetails()->create([
        'amount' => -2,
        'description' => 'Traffic deduction',
        'created_at' => '2026-06-03 08:00:00',
        'updated_at' => '2026-06-03 08:00:00',
    ]);

    $this->artisan('app:credit-activity-cashback-command', [
        'date' => '2026-06-03',
        '--execute' => true,
    ])->assertSuccessful();
    $this->artisan('app:credit-activity-cashback-command', [
        'date' => '2026-06-03',
        '--execute' => true,
    ])->assertSuccessful();

    expect((float) $user->refresh()->balance)->toBe(2.0)
        ->and($user->balanceDetails()->where('description', 'Activity cashback for 2026-06-03')->count())->toBe(1);
});

test('it applies a custom cashback ratio', function () {
    $user = User::factory()->create(['balance' => 0]);
    $balance_detail = $user->balanceDetails()->create([
        'amount' => -2,
        'description' => 'Traffic deduction',
        'created_at' => '2026-06-03 08:00:00',
        'updated_at' => '2026-06-03 08:00:00',
    ]);

    $this->artisan('app:credit-activity-cashback-command', [
        'date' => '2026-06-03',
        '--ratio' => '0.5',
        '--execute' => true,
    ])->assertSuccessful();

    expect((float) $user->refresh()->balance)->toBe(1.0);
});

test('it supports a custom balance detail description', function () {
    $user = User::factory()->create(['balance' => 0]);
    $user->balanceDetails()->create([
        'amount' => -2,
        'description' => 'Traffic deduction',
        'created_at' => '2026-06-03 08:00:00',
        'updated_at' => '2026-06-03 08:00:00',
    ]);

    $this->artisan('app:credit-activity-cashback-command', [
        'date' => '2026-06-03',
        '--description' => 'Dragon Boat consumption bonus {date}',
        '--execute' => true,
    ])->assertSuccessful();

    $this->assertDatabaseHas('balance_details', [
        'user_id' => $user->id,
        'amount' => 2,
        'description' => 'Dragon Boat consumption bonus 2026-06-03',
    ]);
});

test('it previews cashback without crediting by default', function () {
    Bus::fake();

    $user = User::factory()->create(['balance' => 0]);
    $balance_detail = $user->balanceDetails()->create([
        'amount' => -2,
        'description' => 'Traffic deduction',
        'created_at' => '2026-06-03 08:00:00',
        'updated_at' => '2026-06-03 08:00:00',
    ]);

    $this->artisan('app:credit-activity-cashback-command', ['date' => '2026-06-03'])
        ->expectsTable(['User ID', 'Email', 'Balance Detail ID', 'Description', 'Consumed', 'Created At'], [
            [$user->id, $user->email, $balance_detail->id, 'Traffic deduction', '2.00', '2026-06-03 08:00:00'],
        ])
        ->expectsOutput('Would credit 1 users with 2.00 activity cashback for 2026-06-03.')
        ->assertSuccessful();

    expect((float) $user->refresh()->balance)->toBe(0.0);
    $this->assertDatabaseMissing('balance_details', [
        'user_id' => $user->id,
        'amount' => 2,
        'description' => 'Activity cashback for 2026-06-03',
    ]);
    Bus::assertNotDispatched(GenerateClashProfileLink::class);
});

test('it excludes package purchases and other balance deductions from consumption cashback', function () {
    $user = User::factory()->create(['balance' => 0]);
    $user->balanceDetails()->create([
        'amount' => -2,
        'description' => 'Bought package Basic',
        'created_at' => '2026-06-03 08:00:00',
        'updated_at' => '2026-06-03 08:00:00',
    ]);
    $user->balanceDetails()->create([
        'amount' => -1,
        'description' => 'Subscription URL reset',
        'created_at' => '2026-06-03 09:00:00',
        'updated_at' => '2026-06-03 09:00:00',
    ]);
    $user->balanceDetails()->create([
        'amount' => -3,
        'description' => 'AI gpt-4o | 1req in:1 out:1',
        'created_at' => '2026-06-03 10:00:00',
        'updated_at' => '2026-06-03 10:00:00',
    ]);

    $this->artisan('app:credit-activity-cashback-command', [
        'date' => '2026-06-03',
        '--execute' => true,
    ])
        ->expectsOutput('Credited 0 users with 0.00 activity cashback for 2026-06-03.')
        ->assertSuccessful();

    expect((float) $user->refresh()->balance)->toBe(0.0);
    $this->assertDatabaseMissing('balance_details', [
        'user_id' => $user->id,
        'description' => 'Activity cashback for 2026-06-03',
    ]);
});
