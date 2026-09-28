<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardControls;
use App\Services\AdminDashboardReportService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UserActivityWidget extends StatsOverviewWidget
{
    use InteractsWithDashboardControls;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'User Activity';

    protected function getStats(): array
    {
        $stats = app(AdminDashboardReportService::class)->getUserActivityStats();

        return [
            Stat::make('Total Users', number_format($stats['total_users']))
                ->color('gray'),
            Stat::make('New Users (30d)', number_format($stats['new_users_last_30_days']))
                ->color('info'),
            Stat::make('Users with Paid Top-Ups (30d)', number_format($stats['paid_users_last_30_days']))
                ->color('success'),
            Stat::make('Users with Traffic Today', number_format($stats['users_with_traffic_today']))
                ->description('Latest collection')
                ->color('primary'),
        ];
    }
}
