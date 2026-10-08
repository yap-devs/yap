<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DailyTrafficRankingTable;
use App\Filament\Widgets\LastSevenDayTrafficChart;
use App\Filament\Widgets\MonthlyTrafficReportChart;
use App\Filament\Widgets\TotalTrafficLeaderboardTable;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class TrafficReports extends Dashboard
{
    protected static bool $isDiscovered = true;

    protected static ?string $title = 'Traffic Trends';

    protected static string $routePath = 'traffic-reports';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 2;

    public function getSubheading(): ?string
    {
        return 'Collected traffic after route multipliers. Monthly data follows collection snapshots; internal accounts #1–5 are excluded.';
    }

    public function getColumns(): int|array
    {
        return ['md' => 12, 'xl' => 12];
    }

    public function getWidgets(): array
    {
        return [
            MonthlyTrafficReportChart::class,
            LastSevenDayTrafficChart::class,
            DailyTrafficRankingTable::class,
            TotalTrafficLeaderboardTable::class,
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->columns(['md' => 2])->components([
            Select::make('trend_window')
                ->label('Monthly Trend Window')
                ->options(['6' => 'Last 6 months', '12' => 'Last 12 months', '24' => 'Last 24 months'])
                ->default('12')
                ->native(false)
                ->selectablePlaceholder(false),
            Select::make('polling_interval')
                ->label('Auto Refresh')
                ->options(Dashboard::getPollingIntervalOptions())
                ->default('off')
                ->native(false)
                ->selectablePlaceholder(false),
        ]);
    }
}
