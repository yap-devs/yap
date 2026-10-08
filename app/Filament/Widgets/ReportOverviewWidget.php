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

    protected ?string $heading = 'Today at a Glance';

    protected function getStats(): array
    {
        return $this->getTodayMetrics();
    }

    /**
     * @return array<Stat>
     */
    public function getTodayMetrics(): array
    {
        $service = app(AdminDashboardReportService::class);
        $today = $service->getTodayStats();
        $money = $service->getTopUpSnapshotStats();
        $month_traffic = (float) $service->getMonthlyTrafficSeries(1)->last();

        return [
            Stat::make('Paid Top-Ups Today', $this->formatCurrency($today['top_up']))
                ->description('MTD '.$this->formatCurrency($money['current_month_top_up']).' | '.$this->formatMonthToDateChange($money['current_month_top_up'], $money['previous_month_to_date_top_up']))
                ->descriptionIcon('heroicon-m-banknotes', IconPosition::Before)
                ->color('success'),
            Stat::make('Balance Debits Today', $this->formatCurrency($today['usage']))
                ->description('MTD '.$this->formatCurrency($money['current_month_usage']).' | '.$this->formatMonthToDateChange($money['current_month_usage'], $money['previous_month_to_date_usage']))
                ->descriptionIcon('heroicon-m-arrow-trending-down', IconPosition::Before)
                ->color('danger'),
            Stat::make('Traffic Collected Today', $this->formatGigabytes($today['traffic_gb']))
                ->description('MTD '.$this->formatGigabytes($month_traffic))
                ->descriptionIcon('heroicon-m-chart-bar', IconPosition::Before)
                ->chart($service->getLastSevenDayTrafficSeries()->values()->all())
                ->color('info'),
            Stat::make('Users with Traffic Today', number_format($today['active_users']))
                ->description('Distinct users recorded by the latest collection')
                ->descriptionIcon('heroicon-m-users', IconPosition::Before)
                ->color('primary'),
        ];
    }

    private function formatCurrency(float $amount): string
    {
        return number_format($amount, 2).' USD';
    }

    private function formatGigabytes(float $gigabytes): string
    {
        return number_format($gigabytes, 2).' GiB';
    }

    private function formatMonthToDateChange(float $current, float $previous): string
    {
        if ($previous <= 0) {
            return 'Prior MTD '.$this->formatCurrency($previous);
        }

        $change = ($current - $previous) / $previous * 100;

        return sprintf('%+.1f%% vs prior MTD', $change);
    }

    protected function getDescription(): ?string
    {
        return 'Money updates from paid payments and balance changes; traffic follows the latest collection.';
    }
}
