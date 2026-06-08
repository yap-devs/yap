<?php

use App\Models\Payment;
use App\Models\User;
use App\Services\AdminDashboardReportService;
use Carbon\CarbonImmutable;

test('payment top up ranking is scoped to the selected period', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-06-04 12:00:00'));

    $ignored_admin_user = User::factory()->create(['id' => 5, 'name' => 'Admin User']);
    $top_user = User::factory()->create(['name' => 'Top User', 'email' => 'top@example.com']);
    $second_user = User::factory()->create(['name' => 'Second User', 'email' => 'second@example.com']);

    $top_user->payments()->create([
        'gateway' => Payment::GATEWAY_ALIPAY,
        'status' => Payment::STATUS_PAID,
        'amount' => 10,
        'created_at' => '2026-06-04 09:00:00',
    ]);

    $top_user->payments()->create([
        'gateway' => Payment::GATEWAY_STRIPE,
        'status' => Payment::STATUS_PAID,
        'amount' => 5,
        'created_at' => '2026-06-04 10:00:00',
    ]);

    $second_user->payments()->create([
        'gateway' => Payment::GATEWAY_USDT,
        'status' => Payment::STATUS_PAID,
        'amount' => 8,
        'created_at' => '2026-06-04 11:00:00',
    ]);

    $top_user->payments()->create([
        'gateway' => Payment::GATEWAY_ALIPAY,
        'status' => Payment::STATUS_CREATED,
        'amount' => 100,
        'created_at' => '2026-06-04 11:30:00',
    ]);

    $top_user->payments()->create([
        'gateway' => Payment::GATEWAY_ALIPAY,
        'status' => Payment::STATUS_PAID,
        'amount' => 50,
        'created_at' => '2026-06-03 23:59:59',
    ]);

    $ignored_admin_user->payments()->create([
        'gateway' => Payment::GATEWAY_ALIPAY,
        'status' => Payment::STATUS_PAID,
        'amount' => 500,
        'created_at' => '2026-06-04 08:00:00',
    ]);

    $rows = app(AdminDashboardReportService::class)
        ->getPaymentTopUpRankingByPeriodQuery('day')
        ->get();

    expect($rows)->toHaveCount(2)
        ->and((int) $rows[0]->user_id)->toBe($top_user->id)
        ->and((float) $rows[0]->total_top_up)->toBe(15.0)
        ->and((int) $rows[0]->top_up_count)->toBe(2)
        ->and((int) $rows[0]->gateway_count)->toBe(2)
        ->and((int) $rows[1]->user_id)->toBe($second_user->id)
        ->and((float) $rows[1]->total_top_up)->toBe(8.0);
});

test('payment top up ranking supports month quarter and half year periods', function (string $period, float $expected_total) {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-15 12:00:00'));

    $user = User::factory()->create(['id' => 6]);

    foreach ([
        ['amount' => 10, 'created_at' => '2026-08-01 00:00:00'],
        ['amount' => 20, 'created_at' => '2026-07-01 00:00:00'],
        ['amount' => 30, 'created_at' => '2026-06-30 23:59:59'],
        ['amount' => 40, 'created_at' => '2026-01-01 00:00:00'],
    ] as $payment) {
        $user->payments()->create([
            'gateway' => Payment::GATEWAY_ALIPAY,
            'status' => Payment::STATUS_PAID,
            'amount' => $payment['amount'],
            'created_at' => $payment['created_at'],
        ]);
    }

    $row = app(AdminDashboardReportService::class)
        ->getPaymentTopUpRankingByPeriodQuery($period)
        ->first();

    expect((float) $row->total_top_up)->toBe($expected_total);
})->with([
    'month' => ['month', 10.0],
    'quarter' => ['quarter', 30.0],
    'half year' => ['half_year', 30.0],
]);

test('payment top up ranking period falls back to day for invalid values', function () {
    $report_service = app(AdminDashboardReportService::class);

    expect($report_service->normalizePaymentTopUpRankingPeriod('invalid'))->toBe('day')
        ->and($report_service->normalizePaymentTopUpRankingPeriod(null))->toBe('day')
        ->and($report_service->normalizePaymentTopUpRankingPeriod('quarter'))->toBe('quarter');
});

test('payment top up ranking supports custom date ranges', function () {
    $user = User::factory()->create(['id' => 6]);

    foreach ([
        ['amount' => 10, 'created_at' => '2026-03-01 00:00:00'],
        ['amount' => 20, 'created_at' => '2026-03-31 23:59:59'],
        ['amount' => 30, 'created_at' => '2026-04-01 00:00:00'],
    ] as $payment) {
        $user->payments()->create([
            'gateway' => Payment::GATEWAY_ALIPAY,
            'status' => Payment::STATUS_PAID,
            'amount' => $payment['amount'],
            'created_at' => $payment['created_at'],
        ]);
    }

    $row = app(AdminDashboardReportService::class)
        ->applyPaymentTopUpRankingDateRange(
            app(AdminDashboardReportService::class)->getPaymentTopUpRankingBaseQuery(),
            '2026-03-01',
            '2026-03-31',
        )
        ->first();

    expect((float) $row->total_top_up)->toBe(30.0)
        ->and((int) $row->top_up_count)->toBe(2);
});

test('payment top up ranking custom date range ignores invalid dates', function () {
    $user = User::factory()->create(['id' => 6]);

    $user->payments()->create([
        'gateway' => Payment::GATEWAY_ALIPAY,
        'status' => Payment::STATUS_PAID,
        'amount' => 10,
        'created_at' => '2026-03-01 00:00:00',
    ]);

    $row = app(AdminDashboardReportService::class)
        ->applyPaymentTopUpRankingDateRange(
            app(AdminDashboardReportService::class)->getPaymentTopUpRankingBaseQuery(),
            'not-a-date',
            null,
        )
        ->first();

    expect((float) $row->total_top_up)->toBe(10.0);
});

test('payment top up ranking swaps reversed custom date ranges', function () {
    $user = User::factory()->create(['id' => 6]);

    foreach ([
        ['amount' => 10, 'created_at' => '2026-03-30 00:00:00'],
        ['amount' => 20, 'created_at' => '2026-03-31 23:59:59'],
        ['amount' => 30, 'created_at' => '2026-04-01 00:00:00'],
    ] as $payment) {
        $user->payments()->create([
            'gateway' => Payment::GATEWAY_ALIPAY,
            'status' => Payment::STATUS_PAID,
            'amount' => $payment['amount'],
            'created_at' => $payment['created_at'],
        ]);
    }

    $row = app(AdminDashboardReportService::class)
        ->applyPaymentTopUpRankingDateRange(
            app(AdminDashboardReportService::class)->getPaymentTopUpRankingBaseQuery(),
            '2026-03-31',
            '2026-03-30',
        )
        ->first();

    expect((float) $row->total_top_up)->toBe(30.0)
        ->and((int) $row->top_up_count)->toBe(2);
});
