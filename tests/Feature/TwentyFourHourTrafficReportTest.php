<?php

use App\Models\User;
use App\Services\AdminDashboardReportService;
use Carbon\CarbonImmutable;

test('it builds 24 rolling hourly traffic buckets', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-19 12:30:00'));

    $internal_user = User::factory()->create(['id' => 5]);
    $first_user = User::factory()->create(['name' => 'First User']);
    $second_user = User::factory()->create(['name' => 'Second User']);
    $gigabyte = 1024 * 1024 * 1024;

    $first_user->stats()->create([
        'traffic_downlink' => 2 * $gigabyte,
        'traffic_uplink' => $gigabyte,
        'created_at' => '2026-07-18 12:30:00',
    ]);

    $second_user->stats()->create([
        'traffic_downlink' => 4 * $gigabyte,
        'traffic_uplink' => 2 * $gigabyte,
        'created_at' => '2026-07-19 11:45:00',
    ]);

    $internal_user->stats()->create([
        'traffic_downlink' => 8 * $gigabyte,
        'traffic_uplink' => 8 * $gigabyte,
        'created_at' => '2026-07-19 11:45:00',
    ]);

    $first_user->stats()->create([
        'traffic_downlink' => 16 * $gigabyte,
        'traffic_uplink' => 16 * $gigabyte,
        'created_at' => '2026-07-18 12:29:59',
    ]);

    $first_user->stats()->create([
        'traffic_downlink' => 32 * $gigabyte,
        'traffic_uplink' => 32 * $gigabyte,
        'created_at' => '2026-07-19 12:30:00',
    ]);

    $series = app(AdminDashboardReportService::class)->getLastTwentyFourHourTrafficSeries();

    expect($series)->toHaveCount(24)
        ->and($series->keys()->first())->toBe('2026-07-18 12:30')
        ->and($series->keys()->last())->toBe('2026-07-19 11:30')
        ->and($series->get('2026-07-18 12:30'))->toBe([
            'downlink_gb' => 2.0,
            'uplink_gb' => 1.0,
            'total_gb' => 3.0,
        ])
        ->and($series->get('2026-07-18 13:30'))->toBe([
            'downlink_gb' => 0.0,
            'uplink_gb' => 0.0,
            'total_gb' => 0.0,
        ])
        ->and($series->get('2026-07-19 11:30'))->toBe([
            'downlink_gb' => 4.0,
            'uplink_gb' => 2.0,
            'total_gb' => 6.0,
        ]);
});

test('it summarizes traffic and active users in the rolling window', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-19 12:30:00'));

    $internal_user = User::factory()->create(['id' => 5]);
    $first_user = User::factory()->create();
    $second_user = User::factory()->create();
    $gigabyte = 1024 * 1024 * 1024;

    $first_user->stats()->create([
        'traffic_downlink' => 2 * $gigabyte,
        'traffic_uplink' => $gigabyte,
        'created_at' => '2026-07-18 12:30:00',
    ]);

    $first_user->stats()->create([
        'traffic_downlink' => $gigabyte,
        'traffic_uplink' => $gigabyte,
        'created_at' => '2026-07-19 08:00:00',
    ]);

    $second_user->stats()->create([
        'traffic_downlink' => 3 * $gigabyte,
        'traffic_uplink' => $gigabyte,
        'created_at' => '2026-07-19 11:00:00',
    ]);

    $internal_user->stats()->create([
        'traffic_downlink' => 10 * $gigabyte,
        'traffic_uplink' => 10 * $gigabyte,
        'created_at' => '2026-07-19 11:00:00',
    ]);

    $second_user->stats()->create([
        'traffic_downlink' => 20 * $gigabyte,
        'traffic_uplink' => 20 * $gigabyte,
        'created_at' => '2026-07-18 12:29:59',
    ]);

    $overview = app(AdminDashboardReportService::class)->getLastTwentyFourHourTrafficOverview();

    expect($overview)->toBe([
        'total_gb' => 9.0,
        'downlink_gb' => 6.0,
        'uplink_gb' => 3.0,
        'active_users' => 2,
    ]);
});

test('it ranks users by traffic in the rolling window', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-19 12:30:00'));

    $internal_user = User::factory()->create(['id' => 5, 'name' => 'Internal User']);
    $top_user = User::factory()->create(['name' => 'Top User', 'email' => 'top@example.com']);
    $second_user = User::factory()->create(['name' => 'Second User', 'email' => 'second@example.com']);
    $gigabyte = 1024 * 1024 * 1024;

    $top_user->stats()->create([
        'traffic_downlink' => 3 * $gigabyte,
        'traffic_uplink' => $gigabyte,
        'created_at' => '2026-07-18 12:30:00',
    ]);

    $top_user->stats()->create([
        'traffic_downlink' => 2 * $gigabyte,
        'traffic_uplink' => $gigabyte,
        'created_at' => '2026-07-19 11:45:00',
    ]);

    $second_user->stats()->create([
        'traffic_downlink' => 2 * $gigabyte,
        'traffic_uplink' => 2 * $gigabyte,
        'created_at' => '2026-07-19 10:00:00',
    ]);

    $internal_user->stats()->create([
        'traffic_downlink' => 20 * $gigabyte,
        'traffic_uplink' => 20 * $gigabyte,
        'created_at' => '2026-07-19 10:00:00',
    ]);

    $second_user->stats()->create([
        'traffic_downlink' => 40 * $gigabyte,
        'traffic_uplink' => 40 * $gigabyte,
        'created_at' => '2026-07-18 12:29:59',
    ]);

    $rows = app(AdminDashboardReportService::class)
        ->getLastTwentyFourHourTrafficRankingQuery()
        ->get();

    expect($rows)->toHaveCount(2)
        ->and((int) $rows[0]->user_id)->toBe($top_user->id)
        ->and($rows[0]->user_name)->toBe('Top User')
        ->and($rows[0]->user_email)->toBe('top@example.com')
        ->and((int) $rows[0]->traffic_downlink_bytes)->toBe(5 * $gigabyte)
        ->and((int) $rows[0]->traffic_uplink_bytes)->toBe(2 * $gigabyte)
        ->and((int) $rows[0]->total_traffic_bytes)->toBe(7 * $gigabyte)
        ->and($rows[0]->last_activity_at)->toBe('2026-07-19 11:45:00')
        ->and((int) $rows[1]->user_id)->toBe($second_user->id)
        ->and((int) $rows[1]->total_traffic_bytes)->toBe(4 * $gigabyte);
});
