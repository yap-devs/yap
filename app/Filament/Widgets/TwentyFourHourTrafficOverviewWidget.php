<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardControls;
use App\Services\AdminDashboardReportService;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TwentyFourHourTrafficOverviewWidget extends StatsOverviewWidget
{
    use InteractsWithDashboardControls;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Rolling Window Snapshot';

    protected ?string $description = 'Traffic recorded from the current minute back through the previous 24 hours.';

    protected function getStats(): array
    {
        $report_service = app(AdminDashboardReportService::class);
        $overview = $report_service->getLastTwentyFourHourTrafficOverview();
        $traffic_trend = $report_service->getLastTwentyFourHourTrafficSeries()
            ->pluck('total_gb')
            ->values()
            ->all();

        return [
            Stat::make('Total Traffic', $this->formatGigabytes($overview['total_gb']))
                ->description('Combined downlink and uplink')
                ->descriptionIcon('heroicon-m-signal', IconPosition::Before)
                ->chart($traffic_trend)
                ->color('primary'),
            Stat::make('Downlink', $this->formatGigabytes($overview['downlink_gb']))
                ->description('Traffic delivered to users')
                ->descriptionIcon('heroicon-m-arrow-down-tray', IconPosition::Before)
                ->color('info'),
            Stat::make('Uplink', $this->formatGigabytes($overview['uplink_gb']))
                ->description('Traffic uploaded by users')
                ->descriptionIcon('heroicon-m-arrow-up-tray', IconPosition::Before)
                ->color('success'),
            Stat::make('Active Users', number_format($overview['active_users']))
                ->description('Users with recorded traffic')
                ->descriptionIcon('heroicon-m-users', IconPosition::Before)
                ->color('gray'),
        ];
    }

    private function formatGigabytes(float $gigabytes): string
    {
        return number_format($gigabytes, 2).' GB';
    }
}
