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

    public function getOverviewStats(int $months = 12): array
    {
        $monthly_traffic = $this->getMonthlyTrafficSeries($months);
        $monthly_top_up = $this->getMonthlyTopUpSeries($months);
        $monthly_usage = $this->getMonthlyUsageSeries($months);
        $daily_usage = $this->getLastSevenDayUsageSeries();
        $daily_traffic = $this->getLastSevenDayTrafficSeries();
        $today_stats = $this->getTodayStats();
        $current_month = CarbonImmutable::now()->format('Y-m');
        $active_package_query = $this->getActiveUserPackagesQuery();
        $remaining_package_traffic = (float) $active_package_query->sum('remaining_traffic');
        $access_health = $this->getAccessHealthBreakdown();

        return [
            'today_traffic_gb' => $today_stats['traffic_gb'],
            'today_top_up' => $today_stats['top_up'],
            'today_usage' => $today_stats['usage'],
            'today_active_users' => $today_stats['active_users'],
            'today_top_up_orders' => $today_stats['top_up_orders'],
            'current_month_traffic_gb' => (float) $monthly_traffic->get($current_month, 0),
            'current_month_top_up' => (float) $monthly_top_up->get($current_month, 0),
            'current_month_usage' => (float) $monthly_usage->get($current_month, 0),
            'last_7_day_usage' => round((float) $daily_usage->sum(), 2),
            'last_7_day_traffic_gb' => round((float) $daily_traffic->sum(), 2),
            'outstanding_balance' => round((float) $this->getReportableUsersQuery()->where('balance', '>', 0)->sum('balance'), 2),
            'active_package_count' => (clone $active_package_query)->count(),
            'remaining_package_traffic_gb' => $this->bytesToGigabytes($remaining_package_traffic),
            'package_backed_user_count' => (int) $access_health->get('Package-backed', 0),
            'access_at_risk_user_count' => (int) $access_health->get('Low balance', 0) + (int) $access_health->get('Negative balance', 0),
            'paid_order_count' => $this->getReportablePaymentsQuery()
                ->where('created_at', '>=', CarbonImmutable::now()->startOfMonth())
                ->count(),
            'monthly_traffic_trend' => $monthly_traffic->values()->all(),
            'monthly_top_up_trend' => $monthly_top_up->values()->all(),
            'monthly_usage_trend' => $monthly_usage->values()->all(),
            'daily_usage_trend' => $daily_usage->values()->all(),
            'daily_traffic_trend' => $daily_traffic->values()->all(),
        ];
    }

    public function getTodayStats(): array
    {
        $today_start = CarbonImmutable::now()->startOfDay();

        $traffic_bytes = (float) $this->getReportableUserStatsQuery()
            ->where('created_at', '>=', $today_start)
            ->selectRaw('SUM(traffic_downlink + traffic_uplink) as total')
            ->value('total');

        $top_up = (float) $this->getReportablePaymentsQuery()
            ->where('created_at', '>=', $today_start)
            ->sum('amount');

        $usage = (float) $this->getReportableUsageQuery()
            ->where('created_at', '>=', $today_start)
            ->selectRaw('ABS(SUM(amount)) as total')
            ->value('total');

        $active_users = (int) $this->getReportableUserStatsQuery()
            ->where('created_at', '>=', $today_start)
            ->distinct('user_id')
            ->count('user_id');

        $top_up_orders = (int) $this->getReportablePaymentsQuery()
            ->where('created_at', '>=', $today_start)
            ->count();

        return [
            'traffic_gb' => $this->bytesToGigabytes($traffic_bytes),
            'top_up' => round($top_up, 2),
            'usage' => round($usage, 2),
            'active_users' => $active_users,
            'top_up_orders' => $top_up_orders,
        ];
    }

    public function getLastSevenDayTrafficSeries(int $days = 7): Collection
    {
        $rows = $this->getReportableUserStatsQuery()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m-%d') as period")
            ->selectRaw('SUM(traffic_downlink + traffic_uplink) as total_traffic_bytes')
            ->where('created_at', '>=', CarbonImmutable::now()->startOfDay()->subDays($days - 1))
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m-%d')")
            ->orderBy('period')
            ->get();

        return $this->buildDailySeries(
            $days,
            $rows->pluck('total_traffic_bytes', 'period')->map(fn (mixed $value): float => $this->bytesToGigabytes((float) $value)),
        );
    }

    public function getMonthlyTrafficSeries(int $months = 12): Collection
    {
        $rows = $this->getReportableUserStatsQuery()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period")
            ->selectRaw('SUM(traffic_downlink + traffic_uplink) as total_traffic_bytes')
            ->where('created_at', '>=', CarbonImmutable::now()->startOfMonth()->subMonths($months - 1))
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m')")
            ->orderBy('period')
            ->get();

        return $this->buildMonthlySeries(
            $months,
            $rows->pluck('total_traffic_bytes', 'period')->map(fn (mixed $value): float => $this->bytesToGigabytes((float) $value)),
        );
    }

    public function getMonthlyTopUpSeries(int $months = 12): Collection
    {
        $rows = $this->getReportablePaymentsQuery()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period")
            ->selectRaw('SUM(amount) as total_top_up')
            ->where('created_at', '>=', CarbonImmutable::now()->startOfMonth()->subMonths($months - 1))
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m')")
            ->orderBy('period')
            ->get();

        return $this->buildMonthlySeries(
            $months,
            $rows->pluck('total_top_up', 'period')->map(fn (mixed $value): float => round((float) $value, 2)),
        );
    }

    public function getMonthlyUsageSeries(int $months = 12): Collection
    {
        $rows = $this->getReportableUsageQuery()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period")
            ->selectRaw('ABS(SUM(amount)) as total_usage')
            ->where('created_at', '>=', CarbonImmutable::now()->startOfMonth()->subMonths($months - 1))
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m')")
            ->orderBy('period')
            ->get();

        return $this->buildMonthlySeries(
            $months,
            $rows->pluck('total_usage', 'period')->map(fn (mixed $value): float => round((float) $value, 2)),
        );
    }

    public function getLastSevenDayUsageSeries(int $days = 7): Collection
    {
        $rows = $this->getReportableUsageQuery()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m-%d') as period")
            ->selectRaw('ABS(SUM(amount)) as total_usage')
            ->where('created_at', '>=', CarbonImmutable::now()->startOfDay()->subDays($days - 1))
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m-%d')")
            ->orderBy('period')
            ->get();

        return $this->buildDailySeries(
            $days,
            $rows->pluck('total_usage', 'period')->map(fn (mixed $value): float => round((float) $value, 2)),
        );
    }

    public function getGatewayTopUpBreakdown(int $months = 12): Collection
    {
        $rows = $this->getReportablePaymentsQuery()
            ->selectRaw('gateway')
            ->selectRaw('SUM(amount) as total_top_up')
            ->where('created_at', '>=', CarbonImmutable::now()->startOfMonth()->subMonths($months - 1))
            ->groupBy('gateway')
            ->get();

        $breakdown = collect([
            'GitHub Sponsors' => 0.0,
            'Alipay' => 0.0,
            'USDT' => 0.0,
            'Stripe' => 0.0,
            'Other' => 0.0,
        ]);

        foreach ($rows as $row) {
            $gateway = $this->mapGatewayLabel((string) $row->gateway);
            $this->putCollectionFloat(
                $breakdown,
                $gateway,
                round((float) $breakdown->get($gateway, 0) + (float) $row->total_top_up, 2),
            );
        }

        return $breakdown;
    }

    public function getUsageCompositionBreakdown(int $months = 12): Collection
    {
        $rows = $this->getReportableUsageQuery()
            ->select(['description'])
            ->selectRaw('ABS(SUM(amount)) as total_usage')
            ->where('created_at', '>=', CarbonImmutable::now()->startOfMonth()->subMonths($months - 1))
            ->groupBy('description')
            ->get();

        $breakdown = collect([
            'Traffic billing' => 0.0,
            'Package purchases' => 0.0,
            'Subscription resets' => 0.0,
            'Other usage' => 0.0,
        ]);

        foreach ($rows as $row) {
            $category = $this->mapUsageCategory($row->description);
            $this->putCollectionFloat(
                $breakdown,
                $category,
                round((float) $breakdown->get($category, 0) + (float) $row->total_usage, 2),
            );
        }

        return $breakdown;
    }

    public function getAccessHealthBreakdown(): Collection
    {
        $users = $this->getReportableUsersQuery()
            ->withCount([
                'packages as active_package_count' => fn (Builder $query) => $query->where('status', UserPackage::STATUS_ACTIVE),
            ])
            ->get(['id', 'balance']);

        $breakdown = collect([
            'Package-backed' => 0,
            'Healthy balance' => 0,
            'Warm balance' => 0,
            'Low balance' => 0,
            'Negative balance' => 0,
        ]);

        foreach ($users as $user) {
            if ($user->active_package_count > 0) {
                $this->incrementCollectionCounter($breakdown, 'Package-backed');

                continue;
            }

            $balance = (float) $user->balance;

            if ($balance < 0) {
                $this->incrementCollectionCounter($breakdown, 'Negative balance');

                continue;
            }

            if ($balance < 1) {
                $this->incrementCollectionCounter($breakdown, 'Low balance');

                continue;
            }

            if ($balance < 5) {
                $this->incrementCollectionCounter($breakdown, 'Warm balance');

                continue;
            }

            $this->incrementCollectionCounter($breakdown, 'Healthy balance');
        }

        return $breakdown;
    }

    public function getPackageUtilizationBreakdown(): Collection
    {
        $user_packages = $this->getActiveUserPackagesQuery()
            ->with('package:id,traffic_limit')
            ->get();

        $breakdown = collect([
            'Critical <10%' => 0,
            'Low 10-30%' => 0,
            'Stable 30-70%' => 0,
            'Fresh >70%' => 0,
        ]);

        foreach ($user_packages as $user_package) {
            $traffic_limit = max((float) ($user_package->package?->traffic_limit ?? 0), 1);
            $remaining_ratio = min(max((float) $user_package->remaining_traffic / $traffic_limit, 0), 1);

            if ($remaining_ratio < 0.1) {
                $this->incrementCollectionCounter($breakdown, 'Critical <10%');

                continue;
            }

            if ($remaining_ratio < 0.3) {
                $this->incrementCollectionCounter($breakdown, 'Low 10-30%');

                continue;
            }

            if ($remaining_ratio < 0.7) {
                $this->incrementCollectionCounter($breakdown, 'Stable 30-70%');

                continue;
            }

            $this->incrementCollectionCounter($breakdown, 'Fresh >70%');
        }

        return $breakdown;
    }

    public function getDailyTrafficRankingQuery(): Builder
    {
        return $this->getReportableUserStatsQuery()
            ->join('users', 'users.id', '=', 'user_stats.user_id')
            ->selectRaw('MIN(user_stats.id) as id')
            ->selectRaw("DATE_FORMAT(user_stats.created_at, '%Y-%m-%d') as day")
            ->selectRaw('user_stats.user_id')
            ->selectRaw('users.name as user_name')
            ->selectRaw('SUM(user_stats.traffic_downlink + user_stats.traffic_uplink) as daily_traffic_bytes')
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
        return $this->getReportableUsersQuery()
            ->select('users.*')
            ->selectRaw('(users.traffic_downlink + users.traffic_uplink) as total_traffic_bytes')
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

    private function getReportablePaymentsQuery(): Builder
    {
        return Payment::query()
            ->where('status', Payment::STATUS_PAID)
            ->where('user_id', '>', self::REPORTABLE_USER_ID_THRESHOLD);
    }

    private function getReportableUsageQuery(): Builder
    {
        return BalanceDetail::query()
            ->where('amount', '<', 0)
            ->where('user_id', '>', self::REPORTABLE_USER_ID_THRESHOLD);
    }

    private function getReportableUsersQuery(): Builder
    {
        return User::query()->where('id', '>', self::REPORTABLE_USER_ID_THRESHOLD);
    }

    private function getReportableUserStatsQuery(): Builder
    {
        return UserStat::query()->where('user_id', '>', self::REPORTABLE_USER_ID_THRESHOLD);
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

    private function incrementCollectionCounter(Collection $collection, string $key): void
    {
        $collection->put($key, (int) $collection->get($key, 0) + 1);
    }

    private function putCollectionFloat(Collection $collection, string $key, float $value): void
    {
        $collection->put($key, $value);
    }

    private function mapGatewayLabel(string $gateway): string
    {
        return match ($gateway) {
            Payment::GATEWAY_GITHUB => 'GitHub Sponsors',
            Payment::GATEWAY_ALIPAY => 'Alipay',
            Payment::GATEWAY_USDT => 'USDT',
            Payment::GATEWAY_STRIPE => 'Stripe',
            default => 'Other',
        };
    }

    private function mapUsageCategory(?string $description): string
    {
        return match (true) {
            $description === 'Traffic deduction', $description === 'Daily deduction' => 'Traffic billing',
            str_starts_with((string) $description, 'Bought package ') => 'Package purchases',
            $description === 'Subscription URL reset' => 'Subscription resets',
            default => 'Other usage',
        };
    }
}
