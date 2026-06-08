<?php

use App\Models\Package;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\AdminDashboardReportService;

test('package profit stats account for consumed traffic and remaining liability', function () {
    config(['yap.unit_price' => 0.02]);

    $ignored_admin_user = User::factory()->create(['id' => 5]);
    $first_user = User::factory()->create(['id' => 6]);
    $second_user = User::factory()->create(['id' => 7]);
    $third_user = User::factory()->create(['id' => 8]);
    $fourth_user = User::factory()->create(['id' => 9]);

    $small_package = Package::query()->create([
        'name' => 'Small Package',
        'status' => Package::STATUS_ACTIVE,
        'price' => 1,
        'duration_days' => 30,
        'traffic_limit' => 100 * 1024 * 1024 * 1024,
    ]);

    $large_package = Package::query()->create([
        'name' => 'Large Package',
        'status' => Package::STATUS_ACTIVE,
        'price' => 5,
        'duration_days' => 30,
        'traffic_limit' => 100 * 1024 * 1024 * 1024,
    ]);

    UserPackage::query()->create([
        'user_id' => $first_user->id,
        'package_id' => $small_package->id,
        'remaining_traffic' => 40 * 1024 * 1024 * 1024,
        'status' => UserPackage::STATUS_ACTIVE,
    ]);

    UserPackage::query()->create([
        'user_id' => $second_user->id,
        'package_id' => $large_package->id,
        'remaining_traffic' => 100 * 1024 * 1024 * 1024,
        'status' => UserPackage::STATUS_ACTIVE,
    ]);

    UserPackage::query()->create([
        'user_id' => $third_user->id,
        'package_id' => $large_package->id,
        'remaining_traffic' => 80 * 1024 * 1024 * 1024,
        'status' => UserPackage::STATUS_EXPIRED,
    ]);

    UserPackage::query()->create([
        'user_id' => $fourth_user->id,
        'package_id' => $small_package->id,
        'remaining_traffic' => 120 * 1024 * 1024 * 1024,
        'status' => UserPackage::STATUS_ACTIVE,
    ]);

    UserPackage::query()->create([
        'user_id' => $ignored_admin_user->id,
        'package_id' => $large_package->id,
        'remaining_traffic' => 0,
        'status' => UserPackage::STATUS_ACTIVE,
    ]);

    $first_user->balanceDetails()->create([
        'amount' => -1,
        'description' => 'Bought package Small Package',
    ]);

    $second_user->balanceDetails()->create([
        'amount' => -5,
        'description' => 'Bought package Large Package',
    ]);

    $third_user->balanceDetails()->create([
        'amount' => -3,
        'description' => 'Bought package Large Package',
    ]);

    $fourth_user->balanceDetails()->create([
        'amount' => -1,
        'description' => 'Bought package Small Package',
    ]);

    $first_user->balanceDetails()->create([
        'amount' => -10,
        'description' => 'Traffic deduction',
    ]);

    $ignored_admin_user->balanceDetails()->create([
        'amount' => -100,
        'description' => 'Bought package Large Package',
    ]);

    $stats = app(AdminDashboardReportService::class)->getPackageProfitStats();

    expect($stats)->toMatchArray([
        'revenue' => 10.0,
        'consumed_cost' => 1.6,
        'realized_profit' => 8.4,
        'outstanding_liability' => 5.2,
        'expected_profit' => 3.2,
    ]);
});
