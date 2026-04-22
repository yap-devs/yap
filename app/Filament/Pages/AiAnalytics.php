<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AiDailyCostChart;
use App\Filament\Widgets\AiDailyRequestsChart;
use App\Filament\Widgets\AiModelBreakdownChart;
use App\Filament\Widgets\AiMonthlyCostChart;
use App\Filament\Widgets\AiOverviewWidget;
use App\Filament\Widgets\AiRecentUsageTable;
use App\Filament\Widgets\AiUsageRankingTable;
use Filament\Pages\Page;

class AiAnalytics extends Page
{
    protected string $view = 'filament.pages.ai-analytics';

    protected static ?string $title = 'AI Analytics';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 2;

    public function getSubheading(): ?string
    {
        return 'AI usage metrics, cost breakdown, and per-user analytics.';
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
            AiOverviewWidget::class,
            AiDailyCostChart::class,
            AiDailyRequestsChart::class,
            AiMonthlyCostChart::class,
            AiModelBreakdownChart::class,
            AiUsageRankingTable::class,
            AiRecentUsageTable::class,
        ];
    }

    public function getWidgetData(): array
    {
        return [];
    }
}
