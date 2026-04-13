<?php

namespace App\Filament\Widgets;

use App\Services\AdminDashboardReportService;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ReportOverviewWidget extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '15s';

    protected ?string $heading = 'Realtime Business Snapshot';

    protected ?string $description = 'Auto-refreshing traffic, revenue, cost, and package health indicators.';

    protected function getStats(): array
    {
        $report = app(AdminDashboardReportService::class)->getOverviewStats();

        return [
            Stat::make('Monthly Traffic', $this->formatGigabytes($report['current_month_traffic_gb']))
                ->description('Month-to-date consumption')
                ->descriptionIcon('heroicon-m-arrow-trending-up', IconPosition::Before)
                ->chart($report['monthly_traffic_trend'])
                ->color('info'),
            Stat::make('Monthly Income', $this->formatCurrency($report['current_month_income']))
                ->description('Net '.$this->formatCurrency($report['net_income']))
                ->descriptionIcon('heroicon-m-banknotes', IconPosition::Before)
                ->chart($report['monthly_income_trend'])
                ->color('success'),
            Stat::make('Monthly Cost', $this->formatCurrency($report['current_month_cost']))
                ->description('Traffic deduction and daily settlement')
                ->descriptionIcon('heroicon-m-arrow-trending-down', IconPosition::Before)
                ->chart($report['monthly_cost_trend'])
                ->color('danger'),
            Stat::make('Last 7-Day Cost', $this->formatCurrency($report['last_7_day_cost']))
                ->description('Rolling 7-day burn rate')
                ->descriptionIcon('heroicon-m-clock', IconPosition::Before)
                ->chart($report['daily_cost_trend'])
                ->color('warning'),
            Stat::make('Active Packages', number_format($report['active_package_count']))
                ->description($this->formatGigabytes($report['remaining_package_traffic_gb']).' remaining')
                ->descriptionIcon('heroicon-m-cube', IconPosition::Before)
                ->color('primary'),
            Stat::make('Paid Orders', number_format($report['paid_order_count']))
                ->description('Completed this month')
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
}
