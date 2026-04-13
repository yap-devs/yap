<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardControls;
use App\Services\AdminDashboardReportService;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ReportOverviewWidget extends StatsOverviewWidget
{
    use InteractsWithDashboardControls;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Realtime Business Snapshot';

    protected ?string $description = 'Auto-refreshing traffic, top-up, usage, and subscription health indicators.';

    protected function getStats(): array
    {
        $report = app(AdminDashboardReportService::class)->getOverviewStats($this->getTrendWindowMonths());

        return [
            Stat::make('Monthly Traffic', $this->formatGigabytes($report['current_month_traffic_gb']))
                ->description('Month-to-date consumption')
                ->descriptionIcon('heroicon-m-arrow-trending-up', IconPosition::Before)
                ->chart($report['monthly_traffic_trend'])
                ->color('info'),
            Stat::make('Monthly Top-Ups', $this->formatCurrency($report['current_month_top_up']))
                ->description('Paid recharge orders this month')
                ->descriptionIcon('heroicon-m-banknotes', IconPosition::Before)
                ->chart($report['monthly_top_up_trend'])
                ->color('success'),
            Stat::make('Monthly Usage', $this->formatCurrency($report['current_month_usage']))
                ->description('Actual user spend deducted from balances')
                ->descriptionIcon('heroicon-m-arrow-trending-down', IconPosition::Before)
                ->chart($report['monthly_usage_trend'])
                ->color('danger'),
            Stat::make('Outstanding Balance', $this->formatCurrency($report['outstanding_balance']))
                ->description('Positive balance still held for future usage')
                ->descriptionIcon('heroicon-m-wallet', IconPosition::Before)
                ->color('primary'),
            Stat::make('Last 7-Day Usage', $this->formatCurrency($report['last_7_day_usage']))
                ->description('Rolling 7-day actual spend')
                ->descriptionIcon('heroicon-m-clock', IconPosition::Before)
                ->chart($report['daily_usage_trend'])
                ->color('warning'),
            Stat::make('Active Packages', number_format($report['active_package_count']))
                ->description($this->formatGigabytes($report['remaining_package_traffic_gb']).' remaining')
                ->descriptionIcon('heroicon-m-cube', IconPosition::Before)
                ->color('primary'),
            Stat::make('Package-backed Users', number_format($report['package_backed_user_count']))
                ->description('Users currently protected by active packages')
                ->descriptionIcon('heroicon-m-shield-check', IconPosition::Before)
                ->color('info'),
            Stat::make('At-risk Users', number_format($report['access_at_risk_user_count']))
                ->description('Low or negative balance without package coverage')
                ->descriptionIcon('heroicon-m-exclamation-triangle', IconPosition::Before)
                ->color('danger'),
            Stat::make('Top-Up Orders', number_format($report['paid_order_count']))
                ->description('Completed recharge orders this month')
                ->descriptionIcon('heroicon-m-check-badge', IconPosition::Before)
                ->color('gray'),
        ];
    }

    private function formatCurrency(float $amount): string
    {
        return number_format($amount, 2).' USD';
    }

    private function formatGigabytes(float $gigabytes): string
    {
        return number_format($gigabytes, 2).' GB';
    }

    protected function getDescription(): ?string
    {
        return 'Live operational KPIs with trend context from the '.$this->getTrendWindowLabel().'.';
    }
}
