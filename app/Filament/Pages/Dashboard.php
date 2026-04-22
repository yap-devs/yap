<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ActiveUserPackagesTable;
use App\Filament\Widgets\DailyTrafficRankingTable;
use App\Filament\Widgets\GatewayTopUpShareChart;
use App\Filament\Widgets\LastSevenDayTrafficChart;
use App\Filament\Widgets\LastSevenDayUsageChart;
use App\Filament\Widgets\MonthlyTopUpAndUsageChart;
use App\Filament\Widgets\MonthlyTrafficReportChart;
use App\Filament\Widgets\PackageUtilizationHealthChart;
use App\Filament\Widgets\ReportOverviewWidget;
use App\Filament\Widgets\TodaySnapshotChart;
use App\Filament\Widgets\TotalTrafficLeaderboardTable;
use App\Filament\Widgets\UsageCompositionChart;
use App\Filament\Widgets\UserAccessHealthChart;
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

    public function getSubheading(): ?string
    {
        return 'Top-ups represent cash-in, usage represents money actually consumed by metered billing and product actions.';
    }

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
            TodaySnapshotChart::class,
            MonthlyTopUpAndUsageChart::class,
            LastSevenDayTrafficChart::class,
            MonthlyTrafficReportChart::class,
            LastSevenDayUsageChart::class,
            GatewayTopUpShareChart::class,
            UsageCompositionChart::class,
            UserAccessHealthChart::class,
            PackageUtilizationHealthChart::class,
            DailyTrafficRankingTable::class,
            TotalTrafficLeaderboardTable::class,
            ActiveUserPackagesTable::class,
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('trend_window')
                ->label('Trend Window')
                ->options([
                    '6' => 'Last 6 months',
                    '12' => 'Last 12 months',
                    '24' => 'Last 24 months',
                ])
                ->default('12')
                ->native(false)
                ->selectablePlaceholder(false)
                ->helperText('Monthly trend charts and breakdown charts use this reporting window.'),
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
                ->color('primary')
                ->action('refreshDashboard'),
        ];
    }

    public function refreshDashboard(): void
    {
        $this->dispatch('dashboard-refresh');
    }
}
