<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AttentionRequiredWidget;
use App\Filament\Widgets\BackupStatusWidget;
use App\Filament\Widgets\LastSevenDayTrafficChart;
use App\Filament\Widgets\MonthlyTopUpAndUsageChart;
use App\Filament\Widgets\ReportOverviewWidget;
use App\Filament\Widgets\SchedulerStatusWidget;
use App\Services\AdminDashboardReportService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static bool $isDiscovered = false;

    protected static ?string $title = 'Operations Overview';

    public function getSubheading(): ?string
    {
        return 'Paid top-ups, balance debits, collected traffic, and users needing attention.';
    }

    public static function getPollingIntervalOptions(): array
    {
        return [
            '60s' => 'Every 60 seconds',
            '5m' => 'Every 5 minutes',
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
            SchedulerStatusWidget::class,
            BackupStatusWidget::class,
            ReportOverviewWidget::class,
            AttentionRequiredWidget::class,
            MonthlyTopUpAndUsageChart::class,
            LastSevenDayTrafficChart::class,
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->columns([
                'md' => 2,
            ])
            ->components([
                Select::make('polling_interval')
                    ->label('Auto Refresh')
                    ->options(static::getPollingIntervalOptions())
                    ->default('60s')
                    ->native(false)
                    ->selectablePlaceholder(false),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshDashboard')
                ->label('Refresh now')
                ->icon('heroicon-m-arrow-path')
                ->labeledFrom('sm')
                ->color('primary')
                ->action('refreshDashboard'),
        ];
    }

    public function refreshDashboard(): void
    {
        app(AdminDashboardReportService::class)->clearDashboardCache();
        $this->dispatch('dashboard-refresh');
    }
}
