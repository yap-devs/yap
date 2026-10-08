<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\PaymentTopUpPeriodRankingTable;
use Filament\Pages\Page;

class TopUpRanking extends Page
{
    protected string $view = 'filament.pages.top-up-ranking';

    protected static ?string $title = 'Top-Up Ranking';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 4;

    public static function canAccess(): bool
    {
        return auth()->id() === 1;
    }

    public function getSubheading(): ?string
    {
        return 'Paid top-ups ranked by order creation date across day, month, quarter and half-year. Internal accounts #1–5 are excluded.';
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
