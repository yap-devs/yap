<?php

namespace App\Services;

use App\Models\BalanceDetail;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserPackage;
use App\Models\UserStat;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AdminDashboardReportService
{
    public const REPORTABLE_USER_ID_THRESHOLD = 5;

    private const BYTES_PER_GB = 1073741824;

    public function getOverviewStats(): array
    {
        $monthly_traffic = $this->getMonthlyTrafficSeries();
        $monthly_income = $this->getMonthlyIncomeSeries();
        $monthly_cost = $this->getMonthlyCostSeries();
        $daily_cost = $this->getLastSevenDayCostSeries();
        $current_month = CarbonImmutable::now()->format('Y-m');
        $active_package_query = $this->getActiveUserPackagesQuery();
        $remaining_package_traffic = (float) $active_package_query->sum('remaining_traffic');

        return [
            'current_month_traffic_gb' => (float) $monthly_traffic->get($current_month, 0),
            'current_month_income' => (float) $monthly_income->get($current_month, 0),
            'current_month_cost' => (float) $monthly_cost->get($current_month, 0),
            'last_7_day_cost' => round((float) $daily_cost->sum(), 2),
            'active_package_count' => (clone $active_package_query)->count(),
            'remaining_package_traffic_gb' => $this->bytesToGigabytes($remaining_package_traffic),
            'paid_order_count' => Payment::query()
                ->where('status', Payment::STATUS_PAID)
                ->where('user_id', '>', self::REPORTABLE_USER_ID_THRESHOLD)
                ->where('created_at', '>=', CarbonImmutable::now()->startOfMonth())
                ->count(),
            'net_income' => round(
                (float) $monthly_income->get($current_month, 0) - (float) $monthly_cost->get($current_month, 0),
                2,
            ),
            'monthly_traffic_trend' => $monthly_traffic->values()->all(),
            'monthly_income_trend' => $monthly_income->values()->all(),
            'monthly_cost_trend' => $monthly_cost->values()->all(),
            'daily_cost_trend' => $daily_cost->values()->all(),
        ];
    }

    public function getMonthlyTrafficSeries(int $months = 12): Collection
    {
        $rows = UserStat::query()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period")
            ->selectRaw('SUM(traffic_downlink + traffic_uplink) as total_traffic_bytes')
            ->where('user_id', '>', self::REPORTABLE_USER_ID_THRESHOLD)
            ->where('created_at', '>=', CarbonImmutable::now()->startOfMonth()->subMonths($months - 1))
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m')")
            ->orderBy('period')
            ->get();

        return $this->buildMonthlySeries(
            $months,
            $rows->pluck('total_traffic_bytes', 'period')->map(fn (mixed $value): float => $this->bytesToGigabytes((float) $value)),
        );
    }

    public function getMonthlyIncomeSeries(int $months = 12): Collection
    {
        $rows = Payment::query()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period")
            ->selectRaw('SUM(amount) as total_income')
            ->where('status', Payment::STATUS_PAID)
            ->where('user_id', '>', self::REPORTABLE_USER_ID_THRESHOLD)
            ->where('created_at', '>=', CarbonImmutable::now()->startOfMonth()->subMonths($months - 1))
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m')")
            ->orderBy('period')
            ->get();

        return $this->buildMonthlySeries(
            $months,
            $rows->pluck('total_income', 'period')->map(fn (mixed $value): float => round((float) $value, 2)),
        );
    }

    public function getMonthlyCostSeries(int $months = 12): Collection
    {
        $rows = BalanceDetail::query()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period")
            ->selectRaw('ABS(SUM(amount)) as total_cost')
            ->where('amount', '<', 0)
            ->where('user_id', '>', self::REPORTABLE_USER_ID_THRESHOLD)
            ->where('created_at', '>=', CarbonImmutable::now()->startOfMonth()->subMonths($months - 1))
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m')")
            ->orderBy('period')
            ->get();

        return $this->buildMonthlySeries(
            $months,
            $rows->pluck('total_cost', 'period')->map(fn (mixed $value): float => round((float) $value, 2)),
        );
    }

    public function getLastSevenDayCostSeries(int $days = 7): Collection
    {
        $rows = BalanceDetail::query()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m-%d') as period")
            ->selectRaw('ABS(SUM(amount)) as total_cost')
            ->where('amount', '<', 0)
            ->where('user_id', '>', self::REPORTABLE_USER_ID_THRESHOLD)
            ->where('created_at', '>=', CarbonImmutable::now()->startOfDay()->subDays($days - 1))
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m-%d')")
            ->orderBy('period')
            ->get();

        return $this->buildDailySeries(
            $days,
            $rows->pluck('total_cost', 'period')->map(fn (mixed $value): float => round((float) $value, 2)),
        );
    }

    public function getDailyTrafficRankingQuery(): Builder
    {
        return UserStat::query()
            ->join('users', 'users.id', '=', 'user_stats.user_id')
            ->selectRaw('MIN(user_stats.id) as id')
            ->selectRaw("DATE_FORMAT(user_stats.created_at, '%Y-%m-%d') as day")
            ->selectRaw('user_stats.user_id')
            ->selectRaw('users.name as user_name')
            ->selectRaw('SUM(user_stats.traffic_downlink + user_stats.traffic_uplink) as daily_traffic_bytes')
            ->where('user_stats.user_id', '>', self::REPORTABLE_USER_ID_THRESHOLD)
            ->where(function (Builder $query) {
                $query
                    ->whereDate('user_stats.created_at', CarbonImmutable::today())
                    ->orWhereDate('user_stats.created_at', CarbonImmutable::yesterday());
            })
            ->groupByRaw("DATE_FORMAT(user_stats.created_at, '%Y-%m-%d'), user_stats.user_id, users.name")
            ->orderByDesc('day')
            ->orderByDesc('daily_traffic_bytes');
    }

    public function getTotalTrafficLeaderboardQuery(): Builder
    {
        return User::query()
            ->select('users.*')
            ->selectRaw('(users.traffic_downlink + users.traffic_uplink) as total_traffic_bytes')
            ->where('users.id', '>', self::REPORTABLE_USER_ID_THRESHOLD)
            ->orderByDesc('total_traffic_bytes');
    }

    public function getActiveUserPackagesQuery(): Builder
    {
        return UserPackage::query()
            ->with(['package', 'user'])
            ->where('user_id', '>', self::REPORTABLE_USER_ID_THRESHOLD)
            ->where('status', UserPackage::STATUS_ACTIVE)
            ->orderBy('ended_at');
    }

    private function buildMonthlySeries(int $months, Collection $values): Collection
    {
        // Zero-fill missing months so the charts remain visually continuous.
        $series = collect(range($months - 1, 0))
            ->mapWithKeys(fn (int $offset): array => [
                CarbonImmutable::now()->startOfMonth()->subMonths($offset)->format('Y-m') => 0.0,
            ]);

        return $series->replace(
            $values->map(fn (mixed $value): float => round((float) $value, 2))->all(),
        );
    }

    private function buildDailySeries(int $days, Collection $values): Collection
    {
        $series = collect(range($days - 1, 0))
            ->mapWithKeys(fn (int $offset): array => [
                CarbonImmutable::now()->startOfDay()->subDays($offset)->format('Y-m-d') => 0.0,
            ]);

        return $series->replace(
            $values->map(fn (mixed $value): float => round((float) $value, 2))->all(),
        );
    }

    private function bytesToGigabytes(float $bytes): float
    {
        return round($bytes / self::BYTES_PER_GB, 2);
    }
}
