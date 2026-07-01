<?php

use App\Console\Commands\UpdateStatCommand;
use App\Models\Package;
use App\Models\User;
use App\Models\UserPackage;
use Carbon\CarbonImmutable;

test('buying a package queues it after the latest active package', function () {
    $this->travelTo(CarbonImmutable::parse('2026-07-10 10:00:00'));
    config(['affiliate.enabled' => false]);

    $user = User::factory()->create([
        'balance' => 10,
    ]);
    $package = createPackage([
        'price' => 5,
        'duration_days' => 30,
        'traffic_limit' => 1000,
    ]);

    UserPackage::query()->create([
        'user_id' => $user->id,
        'package_id' => $package->id,
        'remaining_traffic' => 200,
        'status' => UserPackage::STATUS_ACTIVE,
        'started_at' => CarbonImmutable::parse('2026-07-01 00:00:00'),
        'ended_at' => CarbonImmutable::parse('2026-07-31 00:00:00'),
    ]);

    $this->actingAs($user)
        ->post(route('package.buy', $package))
        ->assertRedirect(route('package'));

    $queued_package = $user->packages()
        ->where('remaining_traffic', 1000)
        ->firstOrFail();

    expect(CarbonImmutable::parse($queued_package->started_at)->toDateTimeString())->toBe('2026-07-31 00:00:00')
        ->and(CarbonImmutable::parse($queued_package->ended_at)->toDateTimeString())->toBe('2026-08-30 00:00:00');
});

test('queued package starts from actual activation when previous package is used early', function () {
    $this->travelTo(CarbonImmutable::parse('2026-07-10 10:00:00'));

    $user = User::factory()->create([
        'balance' => 0,
        'traffic_unpaid' => 1500,
    ]);
    $package = createPackage([
        'duration_days' => 30,
        'traffic_limit' => 1000,
    ]);
    $current_package = UserPackage::query()->create([
        'user_id' => $user->id,
        'package_id' => $package->id,
        'remaining_traffic' => 1000,
        'status' => UserPackage::STATUS_ACTIVE,
        'started_at' => CarbonImmutable::parse('2026-07-01 00:00:00'),
        'ended_at' => CarbonImmutable::parse('2026-07-31 00:00:00'),
    ]);
    $queued_package = UserPackage::query()->create([
        'user_id' => $user->id,
        'package_id' => $package->id,
        'remaining_traffic' => 1000,
        'status' => UserPackage::STATUS_ACTIVE,
        'started_at' => CarbonImmutable::parse('2026-07-31 00:00:00'),
        'ended_at' => CarbonImmutable::parse('2026-08-30 00:00:00'),
    ]);
    $later_queued_package = UserPackage::query()->create([
        'user_id' => $user->id,
        'package_id' => $package->id,
        'remaining_traffic' => 1000,
        'status' => UserPackage::STATUS_ACTIVE,
        'started_at' => CarbonImmutable::parse('2026-08-30 00:00:00'),
        'ended_at' => CarbonImmutable::parse('2026-09-29 00:00:00'),
    ]);

    billUser($user);

    $current_package->refresh();
    $queued_package->refresh();
    $later_queued_package->refresh();

    expect($current_package->status)->toBe(UserPackage::STATUS_USED)
        ->and($queued_package->remaining_traffic)->toBe(500)
        ->and(CarbonImmutable::parse($queued_package->started_at)->toDateTimeString())->toBe('2026-07-10 10:00:00')
        ->and(CarbonImmutable::parse($queued_package->ended_at)->toDateTimeString())->toBe('2026-08-09 10:00:00')
        ->and(CarbonImmutable::parse($later_queued_package->started_at)->toDateTimeString())->toBe('2026-08-09 10:00:00')
        ->and(CarbonImmutable::parse($later_queued_package->ended_at)->toDateTimeString())->toBe('2026-09-08 10:00:00');
});

test('queued package starts when previous package is exactly used up', function () {
    $this->travelTo(CarbonImmutable::parse('2026-07-10 10:00:00'));

    $user = User::factory()->create([
        'balance' => 0,
        'traffic_unpaid' => 1000,
    ]);
    $package = createPackage([
        'duration_days' => 30,
        'traffic_limit' => 1000,
    ]);
    $current_package = UserPackage::query()->create([
        'user_id' => $user->id,
        'package_id' => $package->id,
        'remaining_traffic' => 1000,
        'status' => UserPackage::STATUS_ACTIVE,
        'started_at' => CarbonImmutable::parse('2026-07-01 00:00:00'),
        'ended_at' => CarbonImmutable::parse('2026-07-31 00:00:00'),
    ]);
    $queued_package = UserPackage::query()->create([
        'user_id' => $user->id,
        'package_id' => $package->id,
        'remaining_traffic' => 1000,
        'status' => UserPackage::STATUS_ACTIVE,
        'started_at' => CarbonImmutable::parse('2026-07-31 00:00:00'),
        'ended_at' => CarbonImmutable::parse('2026-08-30 00:00:00'),
    ]);

    billUser($user);

    $current_package->refresh();
    $queued_package->refresh();

    expect($current_package->status)->toBe(UserPackage::STATUS_USED)
        ->and($queued_package->remaining_traffic)->toBe(1000)
        ->and(CarbonImmutable::parse($queued_package->started_at)->toDateTimeString())->toBe('2026-07-10 10:00:00')
        ->and(CarbonImmutable::parse($queued_package->ended_at)->toDateTimeString())->toBe('2026-08-09 10:00:00');
});

test('package page presents future active packages as queued without a fixed expiry date', function () {
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2026-07-10 10:00:00'));

    $user = User::factory()->create();
    $package = createPackage([
        'duration_days' => 30,
        'traffic_limit' => 1000,
    ]);
    UserPackage::query()->create([
        'user_id' => $user->id,
        'package_id' => $package->id,
        'remaining_traffic' => 200,
        'status' => UserPackage::STATUS_ACTIVE,
        'started_at' => CarbonImmutable::parse('2026-07-01 00:00:00'),
        'ended_at' => CarbonImmutable::parse('2026-07-31 00:00:00'),
        'created_at' => CarbonImmutable::parse('2026-07-01 00:00:00'),
    ]);
    $queued_package = UserPackage::query()->create([
        'user_id' => $user->id,
        'package_id' => $package->id,
        'remaining_traffic' => 1000,
        'status' => UserPackage::STATUS_ACTIVE,
        'started_at' => CarbonImmutable::parse('2026-07-31 00:00:00'),
        'ended_at' => CarbonImmutable::parse('2026-08-30 00:00:00'),
        'created_at' => CarbonImmutable::parse('2026-07-10 10:00:00'),
    ]);

    $response = $this->actingAs($user)->get(route('package'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Package/Index')
        ->where('userPackages.0.id', $queued_package->id)
        ->where('userPackages.0.is_queued', true)
        ->where('userPackages.0.display_status', 'queued')
        ->where('userPackages.0.display_ended_at', null)
        ->where('userPackages.0.validity_days', 30)
        ->where('userPackages.1.is_queued', false)
        ->where('userPackages.1.display_status', UserPackage::STATUS_ACTIVE)
        ->where('userPackages.1.display_ended_at', '2026-07-31 00:00:00')
    );
});

test('package page uses pending activation copy for queued packages without duration', function () {
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2026-07-10 10:00:00'));

    $user = User::factory()->create();
    $package = createPackage([
        'duration_days' => 0,
        'traffic_limit' => 1000,
    ]);
    $queued_package = UserPackage::query()->create([
        'user_id' => $user->id,
        'package_id' => $package->id,
        'remaining_traffic' => 1000,
        'status' => UserPackage::STATUS_ACTIVE,
        'started_at' => CarbonImmutable::parse('2026-07-31 00:00:00'),
        'ended_at' => null,
    ]);

    $response = $this->actingAs($user)->get(route('package'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Package/Index')
        ->where('userPackages.0.id', $queued_package->id)
        ->where('userPackages.0.is_queued', true)
        ->where('userPackages.0.display_ended_at', null)
        ->where('userPackages.0.display_validity', 'queued_pending_activation')
    );
});

function createPackage(array $overrides = []): Package
{
    return Package::query()->create(array_merge([
        'name' => 'Test Package',
        'description' => 'Test',
        'status' => Package::STATUS_ACTIVE,
        'price' => 1,
        'duration_days' => 30,
        'traffic_limit' => 1000,
    ], $overrides));
}

function billUser(User $user): void
{
    $command = app(UpdateStatCommand::class);
    $method = new \ReflectionMethod(UpdateStatCommand::class, 'billUser');
    $method->setAccessible(true);
    $method->invoke($command, $user);
}
