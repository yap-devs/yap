<?php

namespace App\Filament\Widgets;

use App\Models\Payment;
use App\Services\AdminDashboardReportService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
            ->description('Paid payment orders ranked by selected reporting period.')
            ->query($report_service->getPaymentTopUpRankingBaseQuery())
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([10, 25, 50, 100])
            ->striped()
            ->filters([
                SelectFilter::make('period')
                    ->label('Period')
                    ->options([
                        'day' => 'Today',
                        'month' => 'This month',
                        'quarter' => 'This quarter',
                        'half_year' => 'This half-year',
                    ])
                    ->default('day')
                    ->native(false)
                    ->selectablePlaceholder(false)
                    ->query(function (Builder $query, array $data) use ($report_service): Builder {
                        $period = $report_service->normalizePaymentTopUpRankingPeriod($data['value'] ?? null);

                        return $report_service->applyPaymentTopUpRankingPeriod($query, $period);
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
