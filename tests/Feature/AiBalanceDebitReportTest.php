<?php

use App\Filament\Widgets\AiOverviewWidget;
use App\Filament\Widgets\AiUsageRankingTable;
use App\Models\User;
use App\Services\AdminDashboardReportService;
use Livewire\Livewire;

test('ai money reports use rounded balance debits and posting dates while requests keep usage dates', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->setTime(12, 0));
    $user = User::factory()->create(['id' => 6]);
    $internal_user = User::factory()->create(['id' => 5]);
    $user->sub2apiUsageRecords()->create([
        'remote_api_key_id' => 1,
        'payload' => '{}',
        'remote_usage_id' => 1,
        'amount' => 0.011,
        'model' => 'test-model',
        'usage_created_at' => '2026-09-30 23:59:00',
    ]);
    $user->sub2apiUsageRecords()->create([
        'remote_api_key_id' => 1,
        'payload' => '{}',
        'remote_usage_id' => 2,
        'amount' => 0.001,
        'model' => 'test-model',
        'usage_created_at' => '2026-10-01 01:00:00',
    ]);
    foreach ([
        [-0.02, '2026-10-01 00:05:00'],
        [-0.01, '2026-10-01 01:05:00'],
        [-1.23, '2026-09-30 12:00:00'],
        [-4.56, '2026-09-01 12:00:00'],
    ] as [$amount, $date]) {
        $user->balanceDetails()->create(['amount' => $amount, 'description' => 'AI test-model | 1req', 'created_at' => $date]);
    }
    $user->balanceDetails()->create(['amount' => 50, 'description' => 'AI refund']);
    $user->balanceDetails()->create(['amount' => -60, 'description' => 'Traffic deduction']);
    $user->balanceDetails()->create(['amount' => -70]);
    $internal_user->balanceDetails()->create(['amount' => -80, 'description' => 'AI internal']);
    $user->balanceDetails()->create(['amount' => -90, 'description' => 'AI deleted'])->delete();

    $report = app(AdminDashboardReportService::class);
    $overview = $report->getAiOverviewStats();

    expect($overview)->toMatchArray([
        'today_cost' => 0.03,
        'month_cost' => 0.03,
        'seven_day_cost' => 1.26,
        'today_requests' => 1,
    ]);
    expect($report->getRevenueBreakdownStats()['today_ai'])->toBe($overview['today_cost']);
    expect($report->getAiDailyCostSeries(3)->all())->toBe([
        '2026-09-29' => 0.0,
        '2026-09-30' => 1.23,
        '2026-10-01' => 0.03,
    ]);
    expect($report->getAiMonthlyCostSeries(2)->all())->toBe([
        '2026-09' => 5.79,
        '2026-10' => 0.03,
    ]);
    expect($report->getAiDailyRequestSeries(2)->all())->toBe([
        '2026-09-30' => 1.0,
        '2026-10-01' => 1.0,
    ]);
    expect($report->getAiModelBreakdown(1)->first())->toBe([
        'model' => 'test-model',
        'requests' => 1,
        'cost' => 0.0,
    ]);
    $stats = (new ReflectionMethod(AiOverviewWidget::class, 'getStats'))->invoke(app(AiOverviewWidget::class));
    expect($stats[0]->getValue())->toBe('$0.03');
});

test('ai rankings sum balance debits without multiplying requests or dropping cross-day charges', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->setTime(12, 0));
    $user = User::factory()->create(['id' => 6]);
    $delayed_user = User::factory()->create(['id' => 7]);
    foreach ([1, 2] as $id) {
        $user->sub2apiUsageRecords()->create([
            'remote_api_key_id' => 1,
            'payload' => '{}',
            'remote_usage_id' => $id,
            'amount' => 0.001,
            'usage_created_at' => now(),
        ]);
    }
    $user->balanceDetails()->create(['amount' => -0.02, 'description' => 'AI batch one']);
    $user->balanceDetails()->create(['amount' => -0.01, 'description' => 'AI batch two']);
    $delayed_user->balanceDetails()->create(['amount' => -0.06, 'description' => 'AI delayed batch']);

    $rows = app(AdminDashboardReportService::class)->getAiUsageRankingQuery()->get();

    expect($rows->pluck('user_id')->all())->toBe([$delayed_user->id, $user->id]);
    expect((float) $rows[0]->total_cost)->toBe(0.06);
    expect((int) $rows[0]->request_count)->toBe(0);
    expect((float) $rows[1]->total_cost)->toBe(0.03);
    expect((int) $rows[1]->request_count)->toBe(2);

    $this->actingAs(User::factory()->create(['id' => 1]));
    Livewire::test(AiUsageRankingTable::class)
        ->assertCanSeeTableRecords($rows)
        ->sortTable('request_count', 'desc')
        ->assertOk();
});

test('ai debit reports return zero-filled amounts when no debits exist', function () {
    $this->travelTo(now()->setDate(2026, 10, 1)->setTime(12, 0));
    $report = app(AdminDashboardReportService::class);

    expect($report->getAiOverviewStats())->toMatchArray([
        'today_cost' => 0.0,
        'month_cost' => 0.0,
        'seven_day_cost' => 0.0,
        'today_requests' => 0,
    ]);
    expect($report->getAiDailyCostSeries(1)->all())->toBe(['2026-10-01' => 0.0]);
    expect($report->getAiMonthlyCostSeries(1)->all())->toBe(['2026-10' => 0.0]);
    expect($report->getAiUsageRankingQuery()->get())->toBeEmpty();
});
