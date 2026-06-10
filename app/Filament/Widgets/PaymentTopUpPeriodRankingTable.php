<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use App\Services\AdminDashboardReportService;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class PaymentTopUpPeriodRankingTable extends TableWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $report_service = app(AdminDashboardReportService::class);

        return $table
            ->heading('User Top-Up Ranking')
            ->description('Paid payment orders ranked by the selected date range. Defaults to the current month.')
            ->query($report_service->getPaymentTopUpRankingBaseQuery()->reorder())
            ->defaultSort('total_top_up', 'desc')
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([10, 25, 50, 100])
            ->striped()
            ->filters([
                Filter::make('date_range')
                    ->label('Date Range')
                    ->schema([
                        DatePicker::make('start_date')
                            ->label('Start date')
                            ->default(now()->startOfMonth()),
                        DatePicker::make('end_date')
                            ->label('End date')
                            ->default(now()),
                    ])
                    ->query(function (Builder $query, array $data) use ($report_service): Builder {
                        return $report_service->applyPaymentTopUpRankingDateRange(
                            $query,
                            $data['start_date'] ?? null,
                            $data['end_date'] ?? null,
                        );
                    }),
            ])
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

    private function formatCurrency(float $amount): string
    {
        return number_format($amount, 2).' USD';
    }
}
