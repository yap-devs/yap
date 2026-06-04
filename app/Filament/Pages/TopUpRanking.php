<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\PaymentTopUpPeriodRankingTable;
use Filament\Pages\Page;

class TopUpRanking extends Page
{
    protected string $view = 'filament.pages.top-up-ranking';

    protected static ?string $title = 'Top-Up Ranking';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 3;

    public function getSubheading(): ?string
    {
        return 'User recharge amount leaderboard by day, month, quarter, and half-year.';
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
            PaymentTopUpPeriodRankingTable::class,
        ];
    }

    public function getWidgetData(): array
    {
        return [];
    }
}
