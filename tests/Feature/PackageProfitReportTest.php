<?php

use App\Models\Package;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\AdminDashboardReportService;

test('package profit stats only account for ended packages', function () {
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

    $stats = app(AdminDashboardReportService::class)->getPackageProfitStats();

    expect($stats)->toMatchArray([
        'revenue' => 5.0,
        'consumed_cost' => 0.4,
        'realized_profit' => 4.6,
        'outstanding_liability' => 0.0,
        'expected_profit' => 4.6,
    ]);
});

test('user package overview stats follow status filters', function () {
    config(['yap.unit_price' => 0.02]);

    $user = User::factory()->create(['id' => 6]);

    $package = Package::query()->create([
        'name' => 'Filtered Package',
        'status' => Package::STATUS_ACTIVE,
        'price' => 4,
        'duration_days' => 30,
        'traffic_limit' => 100 * 1024 * 1024 * 1024,
    ]);

    UserPackage::query()->create([
        'user_id' => $user->id,
        'package_id' => $package->id,
        'remaining_traffic' => 25 * 1024 * 1024 * 1024,
        'status' => UserPackage::STATUS_USED,
    ]);

    UserPackage::query()->create([
        'user_id' => $user->id,
        'package_id' => $package->id,
        'remaining_traffic' => 90 * 1024 * 1024 * 1024,
        'status' => UserPackage::STATUS_ACTIVE,
    ]);

    $ended_stats = app(AdminDashboardReportService::class)->getUserPackagesOverviewStats('ended');
    $active_stats = app(AdminDashboardReportService::class)->getUserPackagesOverviewStats('active');

    expect($ended_stats)->toMatchArray([
        'package_count' => 1,
        'active_count' => 0,
        'revenue' => 4.0,
        'consumed_cost' => 1.5,
        'expected_profit' => 2.5,
    ])->and($active_stats)->toMatchArray([
        'package_count' => 1,
        'active_count' => 1,
        'revenue' => 0.0,
        'consumed_cost' => 0.0,
        'expected_profit' => 0.0,
        'remaining_traffic_gb' => 90.0,
    ]);
});
