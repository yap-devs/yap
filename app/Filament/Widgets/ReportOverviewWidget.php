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

    protected function getStats(): array
    {
        $service = app(AdminDashboardReportService::class);
        $report = $service->getOverviewStats($this->getTrendWindowMonths());
        $ai = $service->getAiOverviewStats($this->getTrendWindowMonths());

        return [
            // Traffic: today is the headline, month-to-date in description
            Stat::make('Today Traffic', $this->formatGigabytes($report['today_traffic_gb']))
                ->description('MTD '.$this->formatGigabytes($report['current_month_traffic_gb']).' | 7d '.$this->formatGigabytes($report['last_7_day_traffic_gb']))
                ->descriptionIcon('heroicon-m-arrow-trending-up', IconPosition::Before)
                ->chart($report['daily_traffic_trend'])
                ->color('info'),
            // Top-ups: today headline, month-to-date comparison
            Stat::make('Today Top-Ups', $this->formatCurrency($report['today_top_up']))
                ->description('MTD '.$this->formatCurrency($report['current_month_top_up']).' ('.number_format($report['paid_order_count']).' orders)')
                ->descriptionIcon('heroicon-m-banknotes', IconPosition::Before)
                ->chart($report['monthly_top_up_trend'])
                ->color('success'),
            // Usage: today headline, month-to-date comparison
            Stat::make('Today Usage', $this->formatCurrency($report['today_usage']))
                ->description('MTD '.$this->formatCurrency($report['current_month_usage']).' | 7d '.$this->formatCurrency($report['last_7_day_usage']))
                ->descriptionIcon('heroicon-m-arrow-trending-down', IconPosition::Before)
                ->chart($report['daily_usage_trend'])
                ->color('danger'),
            // AI usage
            Stat::make('Today AI Cost', $this->formatCurrency($ai['today_cost']))
                ->description('MTD '.$this->formatCurrency($ai['month_cost']).' | 7d '.$this->formatCurrency($ai['seven_day_cost']).' | '.$ai['today_requests'].' req')
                ->descriptionIcon('heroicon-m-cpu-chip', IconPosition::Before)
                ->chart($ai['daily_cost_trend'])
                ->color('info'),
            // Active users today
            Stat::make('Active Users Today', number_format($report['today_active_users']))
                ->description(number_format($report['package_backed_user_count']).' package-backed | '.number_format($report['access_at_risk_user_count']).' at risk')
                ->descriptionIcon('heroicon-m-users', IconPosition::Before)
                ->color('primary'),
            // Outstanding balance: point-in-time
            Stat::make('Outstanding Balance', $this->formatCurrency($report['outstanding_balance']))
                ->description($this->formatGigabytes($report['remaining_package_traffic_gb']).' package traffic remaining')
                ->descriptionIcon('heroicon-m-wallet', IconPosition::Before)
                ->color('warning'),
            // Active packages: point-in-time
            Stat::make('Active Packages', number_format($report['active_package_count']))
                ->description('Today top-up orders: '.number_format($report['today_top_up_orders']))
                ->descriptionIcon('heroicon-m-cube', IconPosition::Before)
                ->color('gray'),
            // AI keys
            Stat::make('AI Keys', $ai['active_keys'].' active / '.$ai['total_keys'].' total')
                ->description($ai['active_keys'].' active, '.($ai['total_keys'] - $ai['active_keys']).' inactive')
                ->descriptionIcon('heroicon-m-key', IconPosition::Before)
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
        return 'Today\'s live numbers with month-to-date (MTD) and 7-day context. Trend from the '.$this->getTrendWindowLabel().'.';
    }
}
