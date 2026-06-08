<?php

namespace App\Services;

use App\Models\BalanceDetail;
use App\Models\Payment;
use App\Models\Sub2apiUsageRecord;
use App\Models\User;
use App\Models\UserPackage;
use App\Models\UserStat;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class AdminDashboardReportService
{
    public const REPORTABLE_USER_ID_THRESHOLD = 5;

    private const BYTES_PER_GB = 1073741824;

    private const CACHE_TTL_SECONDS = 60;

    private const CACHE_VERSION_KEY = 'admin_dashboard_report_version';

    public function getOverviewStats(int $months = 12): array
    {
        $stats = $this->remember('overview_stats', [$months], function () use ($months): array {
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
            $package_profit = $this->getPackageProfitStats();

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
                'package_revenue' => $package_profit['revenue'],
                'package_consumed_cost' => $package_profit['consumed_cost'],
                'package_realized_profit' => $package_profit['realized_profit'],
                'package_outstanding_liability' => $package_profit['outstanding_liability'],
                'package_expected_profit' => $package_profit['expected_profit'],
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
        });

        return $this->withPackageProfitDefaults($stats);
    }

    public function getTodayStats(): array
    {
        return $this->remember('today_stats', [], function (): array {
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
        });
    }

    public function getLastSevenDayTrafficSeries(int $days = 7): Collection
    {
        return $this->remember('last_seven_day_traffic_series', [$days], function () use ($days): Collection {
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
        });
    }

    public function getMonthlyTrafficSeries(int $months = 12): Collection
    {
        return $this->remember('monthly_traffic_series', [$months], function () use ($months): Collection {
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
        });
    }

    public function getMonthlyTopUpSeries(int $months = 12): Collection
    {
        return $this->remember('monthly_top_up_series', [$months], function () use ($months): Collection {
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
        });
    }

    public function getMonthlyTopUpProjectionSeries(int $months = 12): Collection
    {
        return $this->remember('monthly_top_up_projection_series', [$months], function () use ($months): Collection {
            $top_up = $this->getMonthlyTopUpSeries($months);
            $now = CarbonImmutable::now();
            $current_month = $now->format('Y-m');
            $current_top_up = (float) $top_up->get($current_month, 0);
            $days_in_month = max($now->daysInMonth, 1);
            $elapsed_days = max($now->day, 1);
            $projected_top_up = round($current_top_up / $elapsed_days * $days_in_month, 2);

            return $top_up->map(
                fn (float $value, string $month): ?float => $month === $current_month ? $projected_top_up : null,
            );
        });
    }

    public function getMonthlyUsageSeries(int $months = 12): Collection
    {
        return $this->remember('monthly_usage_series', [$months], function () use ($months): Collection {
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
        });
    }

    public function getLastSevenDayUsageSeries(int $days = 7): Collection
    {
        return $this->remember('last_seven_day_usage_series', [$days], function () use ($days): Collection {
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
        });
    }

    public function getGatewayTopUpBreakdown(int $months = 12): Collection
    {
        return $this->remember('gateway_top_up_breakdown', [$months], function () use ($months): Collection {
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
        });
    }

    public function getUsageCompositionBreakdown(int $months = 12): Collection
    {
        return $this->remember('usage_composition_breakdown', [$months], function () use ($months): Collection {
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
        });
    }

    public function getAccessHealthBreakdown(): Collection
    {
        return $this->remember('access_health_breakdown', [], function (): Collection {
            $active_package_user_ids = UserPackage::query()
                ->select('user_id')
                ->where('user_id', '>', self::REPORTABLE_USER_ID_THRESHOLD)
                ->where('status', UserPackage::STATUS_ACTIVE)
                ->distinct();

            return collect([
                'Package-backed' => (int) $this->getReportableUsersQuery()
                    ->whereIn('id', $active_package_user_ids)
                    ->count(),
                'Healthy balance' => (int) $this->getReportableUsersQuery()
                    ->whereNotIn('id', clone $active_package_user_ids)
                    ->where('balance', '>=', 5)
                    ->count(),
                'Warm balance' => (int) $this->getReportableUsersQuery()
                    ->whereNotIn('id', clone $active_package_user_ids)
                    ->where('balance', '>=', 1)
                    ->where('balance', '<', 5)
                    ->count(),
                'Low balance' => (int) $this->getReportableUsersQuery()
                    ->whereNotIn('id', clone $active_package_user_ids)
                    ->where('balance', '>=', 0)
                    ->where('balance', '<', 1)
                    ->count(),
                'Negative balance' => (int) $this->getReportableUsersQuery()
                    ->whereNotIn('id', clone $active_package_user_ids)
                    ->where('balance', '<', 0)
                    ->count(),
            ]);
        });
    }

    public function getPackageUtilizationBreakdown(): Collection
    {
        return $this->remember('package_utilization_breakdown', [], function (): Collection {
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
        });
    }

    public function getPackageProfitStats(): array
    {
        return $this->remember('package_profit_stats', [], function (): array {
            $unit_price = (float) config('yap.unit_price');
            $revenue = (float) $this->getReportablePackagePurchaseQuery()
                ->selectRaw('ABS(SUM(amount)) as total_revenue')
                ->value('total_revenue');

            $traffic = UserPackage::query()
                ->join('packages', 'packages.id', '=', 'user_packages.package_id')
                ->where('user_packages.user_id', '>', self::REPORTABLE_USER_ID_THRESHOLD)
                ->selectRaw('SUM(CASE WHEN packages.traffic_limit > user_packages.remaining_traffic THEN packages.traffic_limit - user_packages.remaining_traffic ELSE 0 END) as consumed_traffic')
                ->selectRaw('SUM(CASE WHEN user_packages.status = ? THEN user_packages.remaining_traffic ELSE 0 END) as remaining_traffic', [UserPackage::STATUS_ACTIVE])
                ->first();

            $consumed_cost = $this->bytesToGigabytes((float) ($traffic?->consumed_traffic ?? 0)) * $unit_price;
            $outstanding_liability = $this->bytesToGigabytes((float) ($traffic?->remaining_traffic ?? 0)) * $unit_price;

            return [
                'revenue' => round($revenue, 2),
                'consumed_cost' => round($consumed_cost, 2),
                'realized_profit' => round($revenue - $consumed_cost, 2),
                'outstanding_liability' => round($outstanding_liability, 2),
                'expected_profit' => round($revenue - $consumed_cost - $outstanding_liability, 2),
            ];
        });
    }

    public function clearDashboardCache(): void
    {
        Cache::forever(self::CACHE_VERSION_KEY, (string) now()->getTimestampMs());
    }

    public function getDailyTrafficRankingQuery(): Builder
    {
        $yesterday_start = CarbonImmutable::yesterday()->startOfDay();
        $tomorrow_start = CarbonImmutable::tomorrow()->startOfDay();

        return $this->getReportableUserStatsQuery()
            ->join('users', 'users.id', '=', 'user_stats.user_id')
            ->selectRaw('MIN(user_stats.id) as id')
            ->selectRaw("DATE_FORMAT(user_stats.created_at, '%Y-%m-%d') as day")
            ->selectRaw('user_stats.user_id')
            ->selectRaw('users.name as user_name')
            ->selectRaw('SUM(user_stats.traffic_downlink + user_stats.traffic_uplink) as daily_traffic_bytes')
            ->where('user_stats.created_at', '>=', $yesterday_start)
            ->where('user_stats.created_at', '<', $tomorrow_start)
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

    public function getPaymentTopUpRankingQuery(): Builder
    {
        return $this->getPaymentTopUpRankingBaseQuery()
            ->limit(10);
    }

    public function getPaymentTopUpRankingBaseQuery(): Builder
    {
        return $this->buildPaymentTopUpRankingQuery();
    }

    public function getPaymentTopUpRankingByPeriodQuery(string $period): Builder
    {
        [$start_at, $end_at] = $this->getPaymentTopUpRankingPeriodBounds($period);

        return $this->buildPaymentTopUpRankingQuery($start_at, $end_at);
    }

    public function applyPaymentTopUpRankingPeriod(Builder $query, string $period): Builder
    {
        [$start_at, $end_at] = $this->getPaymentTopUpRankingPeriodBounds($period);

        return $query
            ->where('payments.created_at', '>=', $start_at)
            ->where('payments.created_at', '<', $end_at);
    }

    public function normalizePaymentTopUpRankingPeriod(?string $period): string
    {
        return in_array($period, ['day', 'month', 'quarter', 'half_year'], true) ? $period : 'day';
    }

    public function getPaymentTopUpRankingPeriodLabel(string $period): string
    {
        [$start_at, $end_at] = $this->getPaymentTopUpRankingPeriodBounds($period);

        return $start_at->format('Y-m-d').' to '.$end_at->subDay()->format('Y-m-d');
    }

    private function buildPaymentTopUpRankingQuery(?CarbonImmutable $start_at = null, ?CarbonImmutable $end_at = null): Builder
    {
        return Payment::query()
            ->join('users', 'users.id', '=', 'payments.user_id')
            ->where('payments.status', Payment::STATUS_PAID)
            ->where('payments.user_id', '>', self::REPORTABLE_USER_ID_THRESHOLD)
            ->when($start_at, fn (Builder $query): Builder => $query->where('payments.created_at', '>=', $start_at))
            ->when($end_at, fn (Builder $query): Builder => $query->where('payments.created_at', '<', $end_at))
            ->selectRaw('MIN(payments.id) as id')
            ->selectRaw('payments.user_id')
            ->selectRaw('users.name as user_name')
            ->selectRaw('users.email as user_email')
            ->selectRaw('COUNT(*) as top_up_count')
            ->selectRaw('COUNT(DISTINCT payments.gateway) as gateway_count')
            ->selectRaw('SUM(payments.amount) as total_top_up')
            ->selectRaw('MIN(payments.created_at) as first_top_up_at')
            ->selectRaw('MAX(payments.created_at) as last_top_up_at')
            ->groupBy('payments.user_id', 'users.name', 'users.email')
            ->orderByDesc('total_top_up');
    }

    public function getAiOverviewStats(int $months = 12): array
    {
        $today_start = CarbonImmutable::now()->startOfDay();
        $month_start = CarbonImmutable::now()->startOfMonth();
        $seven_days_ago = CarbonImmutable::now()->startOfDay()->subDays(6);

        $base_query = $this->getReportableAiUsageQuery();

        $today_cost = round((float) (clone $base_query)->where('created_at', '>=', $today_start)->sum('amount'), 2);
        $today_requests = (int) (clone $base_query)->where('created_at', '>=', $today_start)->count();
        $month_cost = round((float) (clone $base_query)->where('created_at', '>=', $month_start)->sum('amount'), 2);
        $seven_day_cost = round((float) (clone $base_query)->where('created_at', '>=', $seven_days_ago)->sum('amount'), 2);
        $active_keys = (int) $this->getReportableUsersQuery()->whereNotNull('sub2api_key_id')->where('sub2api_key_status', 'active')->count();
        $total_keys = (int) $this->getReportableUsersQuery()->whereNotNull('sub2api_key_id')->count();

        $daily_cost_trend = $this->getAiDailyCostSeries()->values()->all();

        return [
            'today_cost' => $today_cost,
            'today_requests' => $today_requests,
            'month_cost' => $month_cost,
            'seven_day_cost' => $seven_day_cost,
            'active_keys' => $active_keys,
            'total_keys' => $total_keys,
            'daily_cost_trend' => $daily_cost_trend,
        ];
    }

    public function getAiDailyCostSeries(int $days = 7): Collection
    {
        $rows = $this->getReportableAiUsageQuery()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m-%d') as period")
            ->selectRaw('SUM(amount) as total_cost')
            ->where('created_at', '>=', CarbonImmutable::now()->startOfDay()->subDays($days - 1))
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m-%d')")
            ->orderBy('period')
            ->get();

        return $this->buildDailySeries(
            $days,
            $rows->pluck('total_cost', 'period')->map(fn (mixed $value): float => round((float) $value, 2)),
        );
    }

    public function getAiUsageRankingQuery(): Builder
    {
        return Sub2apiUsageRecord::query()
            ->join('users', 'users.id', '=', 'sub2api_usage_records.user_id')
            ->where('sub2api_usage_records.user_id', '>', self::REPORTABLE_USER_ID_THRESHOLD)
            ->selectRaw('MIN(sub2api_usage_records.id) as id')
            ->selectRaw('sub2api_usage_records.user_id')
            ->selectRaw('users.name as user_name')
            ->selectRaw('users.email as user_email')
            ->selectRaw('COUNT(*) as request_count')
            ->selectRaw('SUM(sub2api_usage_records.amount) as total_cost')
            ->where('sub2api_usage_records.created_at', '>=', CarbonImmutable::now()->startOfDay())
            ->groupBy('sub2api_usage_records.user_id', 'users.name', 'users.email')
            ->orderByDesc('total_cost');
    }

    public function getAiMonthlyCostSeries(int $months = 12): Collection
    {
        $rows = $this->getReportableAiUsageQuery()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as period")
            ->selectRaw('SUM(amount) as total_cost')
            ->where('created_at', '>=', CarbonImmutable::now()->startOfMonth()->subMonths($months - 1))
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m')")
            ->orderBy('period')
            ->get();

        return $this->buildMonthlySeries(
            $months,
            $rows->pluck('total_cost', 'period')->map(fn (mixed $value): float => round((float) $value, 2)),
        );
    }

    public function getAiModelBreakdown(int $months = 12): Collection
    {
        return $this->getReportableAiUsageQuery()
            ->selectRaw('COALESCE(model, \'unknown\') as model_name')
            ->selectRaw('COUNT(*) as request_count')
            ->selectRaw('SUM(amount) as total_cost')
            ->where('created_at', '>=', CarbonImmutable::now()->startOfMonth()->subMonths($months - 1))
            ->groupByRaw('COALESCE(model, \'unknown\')')
            ->orderByDesc('total_cost')
            ->get()
            ->map(fn (object $row): array => [
                'model' => $row->model_name,
                'requests' => (int) $row->request_count,
                'cost' => round((float) $row->total_cost, 2),
            ]);
    }

    public function getAiDailyRequestSeries(int $days = 7): Collection
    {
        $rows = $this->getReportableAiUsageQuery()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m-%d') as period")
            ->selectRaw('COUNT(*) as total_requests')
            ->where('created_at', '>=', CarbonImmutable::now()->startOfDay()->subDays($days - 1))
            ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m-%d')")
            ->orderBy('period')
            ->get();

        return $this->buildDailySeries(
            $days,
            $rows->pluck('total_requests', 'period')->map(fn (mixed $value): float => (float) $value),
        );
    }

    public function getAiRecentUsageQuery(): Builder
    {
        return Sub2apiUsageRecord::query()
            ->join('users', 'users.id', '=', 'sub2api_usage_records.user_id')
            ->where('sub2api_usage_records.user_id', '>', self::REPORTABLE_USER_ID_THRESHOLD)
            ->select([
                'sub2api_usage_records.id',
                'sub2api_usage_records.user_id',
                'users.name as user_name',
                'users.email as user_email',
                'sub2api_usage_records.model',
                'sub2api_usage_records.amount',
                'sub2api_usage_records.usage_created_at',
            ])
            ->orderByDesc('sub2api_usage_records.id');
    }

    private function getReportableAiUsageQuery(): Builder
    {
        return Sub2apiUsageRecord::query()
            ->where('user_id', '>', self::REPORTABLE_USER_ID_THRESHOLD);
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

    private function getReportablePackagePurchaseQuery(): Builder
    {
        return $this->getReportableUsageQuery()
            ->where('description', 'like', 'Bought package %');
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

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function getPaymentTopUpRankingPeriodBounds(string $period): array
    {
        $now = CarbonImmutable::now();

        return match ($period) {
            'day' => [$now->startOfDay(), $now->addDay()->startOfDay()],
            'month' => [$now->startOfMonth(), $now->addMonthNoOverflow()->startOfMonth()],
            'quarter' => [$now->startOfQuarter(), $now->addQuarter()->startOfQuarter()],
            'half_year' => [
                $now->month <= 6 ? $now->startOfYear() : $now->startOfYear()->addMonths(6),
                $now->month <= 6 ? $now->startOfYear()->addMonths(6) : $now->addYear()->startOfYear(),
            ],
            default => throw new InvalidArgumentException('Unsupported payment top-up ranking period.'),
        };
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

    private function remember(string $name, array $arguments, callable $callback): mixed
    {
        $version = Cache::rememberForever(self::CACHE_VERSION_KEY, fn (): string => '1');
        $key = 'admin_dashboard_report:'.$version.':'.$name.':'.md5(serialize($arguments));

        return Cache::remember($key, self::CACHE_TTL_SECONDS, $callback);
    }

    private function withPackageProfitDefaults(array $stats): array
    {
        return $stats + [
            'package_revenue' => 0.0,
            'package_consumed_cost' => 0.0,
            'package_realized_profit' => 0.0,
            'package_outstanding_liability' => 0.0,
            'package_expected_profit' => 0.0,
        ];
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
