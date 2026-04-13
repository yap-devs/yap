<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ActiveUserPackagesTable;
use App\Filament\Widgets\DailyTrafficRankingTable;
use App\Filament\Widgets\LastSevenDayCostChart;
use App\Filament\Widgets\MonthlyFinanceReportChart;
use App\Filament\Widgets\MonthlyTrafficReportChart;
use App\Filament\Widgets\ReportOverviewWidget;
use App\Filament\Widgets\TotalTrafficLeaderboardTable;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static bool $isDiscovered = false;

    protected static ?string $title = 'Operations Dashboard';

    public function getColumns(): int|array
    {
        return [
            'md' => 12,
            'xl' => 12,
        ];
    }

    public function getWidgets(): array
    {
        return [
            ReportOverviewWidget::class,
            MonthlyTrafficReportChart::class,
            MonthlyFinanceReportChart::class,
            LastSevenDayCostChart::class,
            DailyTrafficRankingTable::class,
            TotalTrafficLeaderboardTable::class,
            ActiveUserPackagesTable::class,
        ];
    }
}
