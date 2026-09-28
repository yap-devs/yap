<?php

use App\Filament\Widgets\TodaySnapshotChart;
use App\Models\Payment;
use App\Models\User;
use App\Services\AdminDashboardReportService;
use App\Services\TrafficReportSnapshotService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 9, 15)->setTime(12, 0));
});

test('traffic snapshots preserve reporting scope and share all trend windows', function () {
    $internal_user = User::factory()->create(['id' => 5]);
    $user = User::factory()->create();
    $gigabyte = 1073741824;

    foreach ([
        ['2024-09-01 00:00:00', 100],
        ['2024-10-01 00:00:00', 4],
        ['2026-03-01 00:00:00', 8],
        ['2026-09-14 23:59:59', 2],
        ['2026-09-15 00:00:00', 3],
        ['2026-09-15 11:00:00', 1],
        ['2026-09-16 00:00:00', 100],
    ] as [$created_at, $traffic]) {
        $user->stats()->create([
            'traffic_downlink' => $traffic * $gigabyte,
            'created_at' => $created_at,
        ]);
    }
    $internal_user->stats()->create(['traffic_downlink' => 100 * $gigabyte]);
    $deleted_stat = $user->stats()->create(['traffic_downlink' => 100 * $gigabyte]);
    $deleted_stat->delete();

    $snapshots = app(TrafficReportSnapshotService::class);
    expect($snapshots->refresh())->toBeTrue();
    $reports = app(AdminDashboardReportService::class);

    expect($reports->getMonthlyTrafficSeries(24))->toHaveCount(24)
        ->and($reports->getMonthlyTrafficSeries(24)->get('2024-10'))->toBe(4.0)
        ->and($reports->getMonthlyTrafficSeries(12))->toHaveCount(12)
        ->and($reports->getMonthlyTrafficSeries(6))->toHaveCount(6)
        ->and($reports->getMonthlyTrafficSeries(6)->has('2026-03'))->toBeFalse()
        ->and($reports->getMonthlyTrafficSeries(6)->get('2026-09'))->toBe(6.0)
        ->and($reports->getMonthlyTrafficSeries(6)->get('2026-08'))->toBe(0.0)
        ->and($reports->getLastSevenDayTrafficSeries())->toHaveCount(7)
        ->and($reports->getLastSevenDayTrafficSeries()->get('2026-09-14'))->toBe(2.0)
        ->and($reports->getLastSevenDayTrafficSeries(1))->toHaveCount(1)
        ->and($reports->getTodayStats()['traffic_gb'])->toBe(4.0)
        ->and($reports->getTodayStats()['active_users'])->toBe(1)
        ->and($snapshots->get()['generated_at'])->toBe('2026-09-15 12:00:00');
});

test('polling and dashboard refresh reuse traffic snapshots until collection publishes new data', function () {
    $user = User::factory()->create(['id' => 6]);
    $user->stats()->create(['traffic_downlink' => 1073741824]);
    $reports = app(AdminDashboardReportService::class);
    expect($reports->getMonthlyTrafficSeries()->get('2026-09'))->toBe(1.0);
    $user->stats()->create(['traffic_downlink' => 1073741824]);
    $this->travel(2)->minutes();
    $reports->clearDashboardCache();
    DB::enableQueryLog();

    expect($reports->getMonthlyTrafficSeries()->get('2026-09'))->toBe(1.0)
        ->and($reports->getLastSevenDayTrafficSeries()->get('2026-09-15'))->toBe(1.0)
        ->and($reports->getTodayStats()['traffic_gb'])->toBe(1.0);
    $traffic_queries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'user_stats'));
    DB::disableQueryLog();
    expect($traffic_queries)->toBeEmpty();

    app(TrafficReportSnapshotService::class)->refresh();
    expect($reports->getMonthlyTrafficSeries()->get('2026-09'))->toBe(2.0)
        ->and($reports->getTodayStats()['traffic_gb'])->toBe(2.0);
});

test('failed snapshot rebuilding keeps the previous complete report', function () {
    $snapshots = app(TrafficReportSnapshotService::class);
    $snapshots->refresh();
    $previous = $snapshots->get();
    DB::listen(function (QueryExecuted $query): void {
        if (str_contains($query->sql, 'COUNT(DISTINCT user_id)')) {
            throw new RuntimeException('daily aggregation failed');
        }
    });

    expect(fn () => $snapshots->refresh())->toThrow(RuntimeException::class, 'daily aggregation failed')
        ->and($snapshots->get())->toBe($previous);
});

test('a previous month snapshot does not leak old totals into the new day or month', function () {
    $this->travelTo(now()->setDate(2026, 9, 30)->setTime(23, 59));
    $user = User::factory()->create(['id' => 6]);
    $user->stats()->create(['traffic_downlink' => 1073741824]);
    $snapshots = app(TrafficReportSnapshotService::class);
    $snapshots->refresh();
    $this->travel(2)->minutes();
    $reports = app(AdminDashboardReportService::class);

    expect($reports->getMonthlyTrafficSeries(1)->all())->toBe(['2026-10' => 0.0])
        ->and($reports->getLastSevenDayTrafficSeries(1)->all())->toBe(['2026-10-01' => 0.0])
        ->and($reports->getTodayStats()['traffic_gb'])->toBe(0.0)
        ->and($reports->getTodayStats()['active_users'])->toBe(0);

    $user->stats()->create(['traffic_downlink' => 2147483648]);
    $snapshots->refresh();
    expect($reports->getMonthlyTrafficSeries(1)->all())->toBe(['2026-10' => 2.0])
        ->and($reports->getTodayStats()['traffic_gb'])->toBe(2.0);
});

test('snapshot refresh commands skip concurrent rebuilds', function () {
    $lock = Cache::lock('admin_dashboard_traffic_snapshot:refresh', 300);
    $lock->get();

    try {
        $this->artisan('app:refresh-traffic-report')->assertFailed();
    } finally {
        $lock->release();
    }

    $this->artisan('app:refresh-traffic-report')->assertSuccessful();
});

test('money snapshot charts query only money and preserve monthly projection', function () {
    $internal_user = User::factory()->create(['id' => 5]);
    $user = User::factory()->create();
    foreach ([['2026-09-01', 20], ['2026-09-15', 10], ['2026-08-31', 100]] as [$date, $amount]) {
        $user->payments()->create([
            'gateway' => Payment::GATEWAY_ALIPAY,
            'status' => Payment::STATUS_PAID,
            'amount' => $amount,
            'created_at' => $date.' 00:00:00',
        ]);
    }
    $internal_user->payments()->create([
        'gateway' => Payment::GATEWAY_ALIPAY,
        'status' => Payment::STATUS_PAID,
        'amount' => 100,
    ]);
    $user->balanceDetails()->create(['amount' => -5, 'description' => 'Traffic deduction']);
    $user->balanceDetails()->create(['amount' => 100, 'description' => 'Top-up']);
    DB::enableQueryLog();

    $chart = new class extends TodaySnapshotChart
    {
        public function chartData(): array
        {
            return $this->getData();
        }
    };
    expect($chart->chartData()['datasets'][0]['data'])->toBe([10.0, 30.0, 60.0, 5.0]);
    $traffic_queries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'user_stats'));
    DB::disableQueryLog();
    expect($traffic_queries)->toBeEmpty();
});
