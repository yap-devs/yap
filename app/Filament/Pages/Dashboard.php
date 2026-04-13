<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ActiveUserPackagesTable;
use App\Filament\Widgets\DailyTrafficRankingTable;
use App\Filament\Widgets\LastSevenDayUsageChart;
use App\Filament\Widgets\MonthlyTopUpAndUsageChart;
use App\Filament\Widgets\MonthlyTrafficReportChart;
use App\Filament\Widgets\ReportOverviewWidget;
use App\Filament\Widgets\TotalTrafficLeaderboardTable;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static bool $isDiscovered = false;

    protected static ?string $title = 'Operations Dashboard';

    public static function getPollingIntervalOptions(): array
    {
        return [
            '5s' => 'Every 5 seconds',
            '15s' => 'Every 15 seconds',
            '30s' => 'Every 30 seconds',
            '60s' => 'Every 60 seconds',
            'off' => 'Manual refresh only',
        ];
    }

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
            MonthlyTopUpAndUsageChart::class,
            LastSevenDayUsageChart::class,
            DailyTrafficRankingTable::class,
            TotalTrafficLeaderboardTable::class,
            ActiveUserPackagesTable::class,
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('polling_interval')
                ->label('Auto Refresh')
                ->options(static::getPollingIntervalOptions())
                ->default('15s')
                ->native(false)
                ->selectablePlaceholder(false)
                ->helperText('This setting controls how often all dashboard widgets refresh.'),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshDashboard')
                ->label('Refresh now')
                ->icon('heroicon-m-arrow-path')
                ->color('gray')
                ->action('refreshDashboard'),
        ];
    }

    public function refreshDashboard(): void
    {
        $this->dispatch('dashboard-refresh');
    }
}
