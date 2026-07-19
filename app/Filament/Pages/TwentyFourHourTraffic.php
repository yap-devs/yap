<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\TwentyFourHourTrafficChart;
use App\Filament\Widgets\TwentyFourHourTrafficOverviewWidget;
use App\Filament\Widgets\TwentyFourHourTrafficRankingTable;
use Filament\Actions\Action;
use Filament\Pages\Page;

class TwentyFourHourTraffic extends Page
{
    protected string $view = 'filament.pages.twenty-four-hour-traffic';

    protected static ?string $title = '24-Hour Traffic';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 2;

    public function getSubheading(): ?string
    {
        return 'Rolling hourly bandwidth totals and per-user traffic rankings for the latest 24 hours.';
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
            TwentyFourHourTrafficOverviewWidget::class,
            TwentyFourHourTrafficChart::class,
            TwentyFourHourTrafficRankingTable::class,
        ];
    }

    public function getWidgetData(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshTraffic')
                ->label('Refresh now')
                ->icon('heroicon-m-arrow-path')
                ->labeledFrom('sm')
                ->color('primary')
                ->action('refreshTraffic'),
        ];
    }

    public function refreshTraffic(): void
    {
        $this->dispatch('dashboard-refresh');
    }
}
