<?php

namespace App\Filament\Widgets;

use App\Services\AdminDashboardReportService;
use Filament\Support\Enums\IconPosition;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AiOverviewWidget extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $ai = app(AdminDashboardReportService::class)->getAiOverviewStats();

        return [
            Stat::make('Today AI Cost', '$'.number_format($ai['today_cost'], 2))
                ->description('MTD $'.number_format($ai['month_cost'], 2).' | 7d $'.number_format($ai['seven_day_cost'], 2))
                ->descriptionIcon('heroicon-m-currency-dollar', IconPosition::Before)
                ->chart($ai['daily_cost_trend'])
                ->color('danger'),
            Stat::make('Today Requests', number_format($ai['today_requests']))
                ->description($ai['today_requests'] > 0 ? 'Avg $'.number_format($ai['today_requests'] > 0 ? $ai['today_cost'] / $ai['today_requests'] : 0, 4).'/req' : 'No requests yet')
                ->descriptionIcon('heroicon-m-bolt', IconPosition::Before)
                ->chart($ai['daily_cost_trend'])
                ->color('info'),
            Stat::make('Active Keys', $ai['active_keys'].' / '.$ai['total_keys'])
                ->description($ai['active_keys'].' active, '.($ai['total_keys'] - $ai['active_keys']).' inactive')
                ->descriptionIcon('heroicon-m-key', IconPosition::Before)
                ->color('success'),
        ];
    }
}
