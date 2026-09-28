<?php

namespace App\Services;

use App\Models\UserStat;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class TrafficReportSnapshotService
{
    private const CACHE_KEY = 'admin_dashboard_traffic_snapshot:v1';

    private const LOCK_KEY = 'admin_dashboard_traffic_snapshot:refresh';

    /**
     * @return array{generated_at: string, monthly_bytes: array<string, float>, daily_bytes: array<string, float>, daily_active_users: array<string, int>}
     */
    public function get(): array
    {
        $snapshot = Cache::get(self::CACHE_KEY);

        if ($snapshot !== null) {
            return $snapshot;
        }

        return Cache::lock(self::LOCK_KEY, 300)->block(10, function (): array {
            return Cache::rememberForever(self::CACHE_KEY, fn (): array => $this->build());
        });
    }

    public function refresh(): bool
    {
        return (bool) Cache::lock(self::LOCK_KEY, 300)->get(function (): bool {
            // Publish only after every query succeeds, keeping the previous snapshot on failure.
            $snapshot = $this->build();
            Cache::forever(self::CACHE_KEY, $snapshot);
            app(AdminDashboardReportService::class)->clearDashboardCache();

            return true;
        });
    }

    private function build(): array
    {
        $now = CarbonImmutable::now();
        $query = UserStat::query()
            ->where('user_id', '>', AdminDashboardReportService::REPORTABLE_USER_ID_THRESHOLD)
            ->where('created_at', '<=', $now);
        $sqlite = $query->getModel()->getConnection()->getDriverName() === 'sqlite';
        $month_expression = $sqlite ? "strftime('%Y-%m', created_at)" : "DATE_FORMAT(created_at, '%Y-%m')";
        $day_expression = $sqlite ? "strftime('%Y-%m-%d', created_at)" : "DATE_FORMAT(created_at, '%Y-%m-%d')";
        $monthly = (clone $query)
            ->selectRaw($month_expression.' as period')
            ->selectRaw('SUM(traffic_downlink + traffic_uplink) as total_traffic_bytes')
            ->where('created_at', '>=', $now->startOfMonth()->subMonths(23))
            ->groupByRaw($month_expression)
            ->pluck('total_traffic_bytes', 'period');
        $daily = (clone $query)
            ->selectRaw($day_expression.' as period')
            ->selectRaw('SUM(traffic_downlink + traffic_uplink) as total_traffic_bytes')
            ->selectRaw('COUNT(DISTINCT user_id) as active_users')
            ->where('created_at', '>=', $now->startOfDay()->subDays(6))
            ->groupByRaw($day_expression)
            ->get();

        return [
            'generated_at' => $now->toDateTimeString(),
            'monthly_bytes' => $monthly->map(fn (mixed $value): float => (float) $value)->all(),
            'daily_bytes' => $daily->pluck('total_traffic_bytes', 'period')->map(fn (mixed $value): float => (float) $value)->all(),
            'daily_active_users' => $daily->pluck('active_users', 'period')->map(fn (mixed $value): int => (int) $value)->all(),
        ];
    }
}
