<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use App\Services\AdminDashboardReportService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;

class PaymentTopUpPeriodRankingTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $period = (string) ($this->pageFilters['period'] ?? 'day');
        $report_service = app(AdminDashboardReportService::class);

        return $table
            ->heading('User Top-Up Ranking')
            ->description('Paid payment orders from '.$report_service->getPaymentTopUpRankingPeriodLabel($period).'.')
            ->query($report_service->getPaymentTopUpRankingByPeriodQuery($period))
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([10, 25, 50, 100])
            ->striped()
            ->columns([
                TextColumn::make('rank')
                    ->label('#')
                    ->rowIndex(),
                TextColumn::make('user_name')
                    ->label('User')
                    ->description(fn (Payment $record): string => (string) $record->user_email)
                    ->searchable(),
                TextColumn::make('top_up_count')
                    ->label('Orders')
                    ->alignEnd()
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->formatStateUsing(fn (mixed $state): string => number_format((int) $state)),
                TextColumn::make('gateway_count')
                    ->label('Gateways')
                    ->alignEnd()
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (mixed $state): string => number_format((int) $state)),
                TextColumn::make('total_top_up')
                    ->label('Total')
                    ->alignEnd()
                    ->badge()
                    ->color('success')
                    ->sortable()
                    ->formatStateUsing(fn (mixed $state): string => $this->formatCurrency((float) $state)),
                TextColumn::make('first_top_up_at')
                    ->label('First Top-Up')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('last_top_up_at')
                    ->label('Last Top-Up')
                    ->dateTime('Y-m-d H:i')
                    ->since()
                    ->sortable(),
            ]);
    }

    public function updatedPageFilters(): void
    {
        if (method_exists($this, 'flushCachedTableRecords')) {
            $this->flushCachedTableRecords();
        }
    }

    private function formatCurrency(float $amount): string
    {
        return number_format($amount, 2).' USD';
    }
}
