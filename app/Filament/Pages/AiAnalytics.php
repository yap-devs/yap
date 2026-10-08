<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AiDailyCostChart;
use App\Filament\Widgets\AiDailyRequestsChart;
use App\Filament\Widgets\AiModelBreakdownChart;
use App\Filament\Widgets\AiMonthlyCostChart;
use App\Filament\Widgets\AiOverviewWidget;
use App\Filament\Widgets\AiRecentUsageTable;
use App\Filament\Widgets\AiUsageRankingTable;

class AiAnalytics extends CashFlow
{
    protected static bool $isDiscovered = true;

    protected static string $routePath = 'ai-analytics';

    protected static ?string $navigationLabel = 'AI Analytics';

    protected static ?string $title = 'AI Analytics';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 5;

    public function getSubheading(): ?string
    {
        return 'AI costs, request volume and account usage. Business metrics exclude internal accounts #1–5.';
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
}
