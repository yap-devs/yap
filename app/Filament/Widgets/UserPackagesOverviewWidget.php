<?php

namespace App\Filament\Widgets;

use App\Services\AdminDashboardReportService;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UserPackagesOverviewWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Ended Package Profit';

    protected function getStats(): array
    {
        $report_service = app(AdminDashboardReportService::class);
        $status = $report_service->normalizeUserPackageStatus($this->pageFilters['status'] ?? 'ended');
        $stats = $report_service->getUserPackagesOverviewStats($status);
        $active_stats = $report_service->getUserPackagesOverviewStats('active');

        return [
            Stat::make($status === 'active' ? 'Unsettled Profit' : 'Package Profit', $this->formatCurrency($stats['expected_profit']))
                ->description('Revenue '.$this->formatCurrency($stats['revenue']).' | Cost '.$this->formatCurrency($stats['consumed_cost']))
                ->descriptionIcon('heroicon-m-scale', IconPosition::Before)
                ->color($stats['expected_profit'] >= 0 ? 'success' : 'danger'),
            Stat::make('Filtered Packages', number_format($stats['package_count']))
                ->description(number_format($stats['active_count']).' active in current filter')
                ->descriptionIcon('heroicon-m-archive-box', IconPosition::Before)
                ->color('gray'),
            Stat::make('Active Packages', number_format($active_stats['package_count']))
                ->description($this->formatGigabytes($active_stats['remaining_traffic_gb']).' traffic remaining')
                ->descriptionIcon('heroicon-m-cube', IconPosition::Before)
                ->color('info'),
        ];
    }

    protected function getDescription(): ?string
    {
        return 'Profit is settled only for expired or fully used packages. Active package profit is shown as 0 until ended.';
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
