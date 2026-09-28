<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\GatewayTopUpShareChart;
use App\Filament\Widgets\LastSevenDayUsageChart;
use App\Filament\Widgets\MonthlyTopUpAndUsageChart;
use App\Filament\Widgets\PaymentTopUpRankingTable;
use App\Filament\Widgets\UsageCompositionChart;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

class CashFlow extends Dashboard
{
    protected static bool $isDiscovered = true;

    protected static ?string $title = 'Cash Flow & Balance Debits';

    protected static ?string $navigationLabel = 'Cash Flow';

    protected static string $routePath = 'cash-flow';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 1;

    public function getSubheading(): ?string
    {
        return 'Paid top-ups are cash received; balance debits are charges for traffic and product actions.';
    }

    public function getColumns(): int|array
    {
        return ['md' => 12, 'xl' => 12];
    }

    public function getWidgets(): array
    {
        return [
            MonthlyTopUpAndUsageChart::class,
            LastSevenDayUsageChart::class,
            GatewayTopUpShareChart::class,
            UsageCompositionChart::class,
            PaymentTopUpRankingTable::class,
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->columns(['md' => 2])->components([
            Select::make('trend_window')
                ->label('Monthly Analysis Window')
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
