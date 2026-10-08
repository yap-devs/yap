<?php

use App\Filament\Pages\AccessHealth;
use App\Filament\Pages\CashFlow;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\TrafficReports;
use App\Filament\Widgets\AtRiskPackagesTable;
use App\Filament\Widgets\AtRiskUsersTable;
use App\Filament\Widgets\AttentionRequiredWidget;
use App\Filament\Widgets\BackupStatusWidget;
use App\Filament\Widgets\GatewayTopUpShareChart;
use App\Filament\Widgets\LastSevenDayTrafficChart;
use App\Filament\Widgets\MonthlyTopUpAndUsageChart;
use App\Filament\Widgets\MonthlyTrafficReportChart;
use App\Filament\Widgets\ReportOverviewWidget;
use App\Filament\Widgets\SchedulerStatusWidget;
use App\Filament\Widgets\UsageCompositionChart;
use App\Filament\Widgets\UserActivityWidget;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserPackage;
use App\Services\AdminDashboardReportService;
use App\Services\TrafficReportSnapshotService;
use Filament\Facades\Filament;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

test('overview is concise and detailed reports remain available on dedicated pages', function () {
    $this->actingAs(User::factory()->create(['id' => 1]));

    expect(app(Dashboard::class)->getWidgets())->toBe([
        SchedulerStatusWidget::class,
        BackupStatusWidget::class,
        ReportOverviewWidget::class,
        AttentionRequiredWidget::class,
        MonthlyTopUpAndUsageChart::class,
        LastSevenDayTrafficChart::class,
    ])->and(app(CashFlow::class)->getWidgets())->toContain(
        MonthlyTopUpAndUsageChart::class,
        GatewayTopUpShareChart::class,
        UsageCompositionChart::class,
    )->and(app(TrafficReports::class)->getWidgets())->toContain(MonthlyTrafficReportChart::class)
        ->and(app(AccessHealth::class)->getWidgets())->toContain(UserActivityWidget::class, AtRiskUsersTable::class, AtRiskPackagesTable::class)
        ->and(Dashboard::getPollingIntervalOptions())->not->toHaveKey('5s');

    Livewire::test(Dashboard::class)->assertOk();
    Livewire::test(CashFlow::class)->assertOk();
    Livewire::test(TrafficReports::class)->assertOk();
    Livewire::test(AccessHealth::class)->assertOk();
});

test('admin navigation follows customer and operations workflows', function () {
    config(['node_agent.enabled' => false]);
    $this->actingAs(User::factory()->create(['id' => 1]));
    Filament::setCurrentPanel('admin');

    $navigation = collect(Filament::getNavigation())
        ->mapWithKeys(fn ($group): array => [
            $group->getLabel() ?? 'Overview' => collect($group->getItems())
                ->map(fn ($item): string => $item->getLabel())
                ->values()
                ->all(),
        ])
        ->all();

    expect($navigation)->toBe([
        'Overview' => ['Operations Overview'],
        'Customers' => ['Users', 'User Packages', 'Access Health'],
        'Reports' => ['Cash Flow', 'Traffic Trends', '24-Hour Traffic', 'Top-Up Ranking', 'AI Analytics'],
        'Affiliates' => ['Promoters', 'Referral Codes', 'Referrals', 'Commissions', 'Levels'],
    ]);
});

test('saved dashboard filters cannot request unsupported report windows or rapid polling', function () {
    $widget = app(MonthlyTopUpAndUsageChart::class);
    $widget->pageFilters = ['trend_window' => '100000', 'polling_interval' => '5s'];

    expect((new ReflectionMethod($widget, 'getTrendWindowMonths'))->invoke($widget))->toBe(12)
        ->and((new ReflectionMethod($widget, 'getPollingInterval'))->invoke($widget))->toBe('60s');

    $widget->pageFilters = ['trend_window' => '24', 'polling_interval' => 'off'];
    expect((new ReflectionMethod($widget, 'getTrendWindowMonths'))->invoke($widget))->toBe(24)
        ->and((new ReflectionMethod($widget, 'getPollingInterval'))->invoke($widget))->toBeNull();
});

test('changing local filters preserves shared cache and manual refresh invalidates it once', function () {
    $this->actingAs(User::factory()->create(['id' => 1]));
    Cache::forever('admin_dashboard_report_version', 'stable');
    $widget = app(MonthlyTopUpAndUsageChart::class);
    $widget->updatedPageFilters();

    expect(Cache::get('admin_dashboard_report_version'))->toBe('stable');
    Livewire::test(Dashboard::class)
        ->call('refreshDashboard')
        ->assertDispatched('dashboard-refresh');
    expect(Cache::get('admin_dashboard_report_version'))->not->toBe('stable');
});

test('user activity distinguishes signups, paying users, and collected traffic', function () {
    $this->travelTo(now()->setDate(2026, 9, 15)->setTime(12, 0));
    User::factory()->create(['id' => 5]);
    $recent_user = User::factory()->create(['id' => 6]);
    $older_user = User::factory()->create(['id' => 7, 'created_at' => '2026-08-01 00:00:00']);
    $older_user->payments()->create([
        'gateway' => Payment::GATEWAY_ALIPAY,
        'status' => Payment::STATUS_PAID,
        'amount' => 5,
    ]);
    $older_user->payments()->create([
        'gateway' => Payment::GATEWAY_ALIPAY,
        'status' => Payment::STATUS_PAID,
        'amount' => 10,
    ]);
    $recent_user->payments()->create([
        'gateway' => Payment::GATEWAY_ALIPAY,
        'status' => Payment::STATUS_CREATED,
        'amount' => 100,
    ]);
    $recent_user->stats()->create(['traffic_downlink' => 1073741824]);
    app(TrafficReportSnapshotService::class)->refresh();

    expect(app(AdminDashboardReportService::class)->getUserActivityStats())->toBe([
        'total_users' => 2,
        'new_users_last_30_days' => 1,
        'paid_users_last_30_days' => 1,
        'users_with_traffic_today' => 1,
    ]);
});

test('cash indicators compare the same elapsed part of each month without counting internal users', function () {
    $this->travelTo(now()->setDate(2026, 9, 15)->setTime(12, 0));
    $internal_user = User::factory()->create(['id' => 5]);
    $user = User::factory()->create(['id' => 6]);

    foreach ([
        ['2026-09-01 09:00:00', 20],
        ['2026-09-15 09:00:00', 10],
        ['2026-08-15 11:00:00', 20],
        ['2026-08-15 13:00:00', 100],
    ] as [$date, $amount]) {
        $user->payments()->create([
            'gateway' => Payment::GATEWAY_ALIPAY,
            'status' => Payment::STATUS_PAID,
            'amount' => $amount,
            'created_at' => $date,
        ]);
    }
    $internal_user->payments()->create([
        'gateway' => Payment::GATEWAY_ALIPAY,
        'status' => Payment::STATUS_PAID,
        'amount' => 999,
    ]);
    $user->balanceDetails()->create(['amount' => -5, 'created_at' => '2026-09-15 09:00:00']);
    $user->balanceDetails()->create(['amount' => -10, 'created_at' => '2026-08-15 11:00:00']);
    $user->balanceDetails()->create(['amount' => -100, 'created_at' => '2026-08-15 13:00:00']);

    $money = app(AdminDashboardReportService::class)->getTopUpSnapshotStats();
    expect($money)->toMatchArray([
        'today_top_up' => 10.0,
        'current_month_top_up' => 30.0,
        'previous_month_to_date_top_up' => 20.0,
        'current_month_usage' => 5.0,
        'previous_month_to_date_usage' => 10.0,
    ]);

    $stats = (new ReflectionMethod(ReportOverviewWidget::class, 'getStats'))
        ->invoke(app(ReportOverviewWidget::class));
    expect($stats[0]->getLabel())->toBe('Paid Top-Ups Today')
        ->and($stats[0]->getDescription())->toContain('+50.0% vs prior MTD')
        ->and($stats[1]->getLabel())->toBe('Balance Debits Today')
        ->and($stats[1]->getDescription())->toContain('-50.0% vs prior MTD');
});

test('monthly chart shows a clearly labeled estimate only above actual current-month top-ups', function () {
    $this->travelTo(now()->setDate(2026, 9, 15)->setTime(12, 0));
    $user = User::factory()->create(['id' => 6]);
    $user->payments()->create([
        'gateway' => Payment::GATEWAY_ALIPAY,
        'status' => Payment::STATUS_PAID,
        'amount' => 30,
        'created_at' => '2026-09-15 09:00:00',
    ]);

    $chart = new class extends MonthlyTopUpAndUsageChart
    {
        public function chartData(): array
        {
            return $this->getData();
        }
    };
    $data = $chart->chartData();
    $current_month = array_search('2026-09', $data['labels'], true);

    expect($data['datasets'][0]['data'][$current_month])->toBe(30.0)
        ->and($data['datasets'][1]['label'])->toBe('Estimated Month-End Top-Ups (USD)')
        ->and($data['datasets'][1]['data'][$current_month])->toBe([30.0, 60.0])
        ->and(array_filter($data['datasets'][1]['data']))->toHaveCount(1);
});

test('attention cards agree with actionable user and package lists', function () {
    $this->actingAs(User::factory()->create(['id' => 1]));
    $internal_user = User::factory()->create(['id' => 5, 'balance' => -5]);
    $negative_user = User::factory()->create(['id' => 6, 'balance' => -2]);
    $low_user = User::factory()->create(['id' => 7, 'balance' => 0.5]);
    $package_user = User::factory()->create(['id' => 8, 'balance' => 0]);
    $healthy_user = User::factory()->create(['id' => 9, 'balance' => 10]);
    $package = Package::query()->create([
        'name' => 'Monthly',
        'status' => Package::STATUS_ACTIVE,
        'price' => 5,
        'duration_days' => 30,
        'traffic_limit' => 100,
    ]);

    foreach ([
        [$internal_user, 5],
        [$package_user, 5],
        [$healthy_user, 50],
    ] as [$user, $remaining]) {
        UserPackage::query()->create([
            'user_id' => $user->id,
            'package_id' => $package->id,
            'remaining_traffic' => $remaining,
            'status' => UserPackage::STATUS_ACTIVE,
        ]);
    }

    $risk_users = app(AtRiskUsersTable::class);
    $risk_packages = app(AtRiskPackagesTable::class);
    $user_ids = $risk_users->table(Table::make($risk_users))->getQuery()->pluck('id')->all();
    $package_user_ids = $risk_packages->table(Table::make($risk_packages))->getQuery()->pluck('user_id')->all();
    $cards = (new ReflectionMethod(AttentionRequiredWidget::class, 'getStats'))
        ->invoke(app(AttentionRequiredWidget::class));

    expect($user_ids)->toEqualCanonicalizing([$negative_user->id, $low_user->id])
        ->and($package_user_ids)->toBe([$package_user->id])
        ->and($cards[0]->getValue())->toBe('2')
        ->and($cards[1]->getValue())->toBe('1')
        ->and($cards[0]->getUrl())->toBe(AccessHealth::getUrl());
});

test('operational status widgets follow dashboard polling and manual refresh', function (string $widget_class) {
    $this->actingAs(User::factory()->create(['id' => 1]));
    Livewire::test($widget_class, ['pageFilters' => ['polling_interval' => 'off']])
        ->assertDontSeeHtml('wire:poll')
        ->dispatch('dashboard-refresh')
        ->assertOk();
    Livewire::test($widget_class, ['pageFilters' => ['polling_interval' => '5m']])
        ->assertSeeHtml('wire:poll.5m');
})->with(['backup' => [BackupStatusWidget::class], 'scheduler' => [SchedulerStatusWidget::class]]);
