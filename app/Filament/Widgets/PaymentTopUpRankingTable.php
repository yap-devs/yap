<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardControls;
use App\Models\Payment;
use App\Services\AdminDashboardReportService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class PaymentTopUpRankingTable extends TableWidget
{
    use InteractsWithDashboardControls;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = [
        'md' => 6,
        'xl' => 6,
    ];

    public function table(Table $table): Table
    {
        return $table
            ->heading('Payment Top-Up Users')
            ->description('Top 10 users ranked by paid payment order amount.')
            ->query(app(AdminDashboardReportService::class)->getPaymentTopUpRankingQuery())
            ->poll(fn (): ?string => $this->getPollingInterval())
            ->paginated(false)
            ->striped()
            ->columns([
                TextColumn::make('rank')
                    ->label('#')
                    ->rowIndex(),
                TextColumn::make('user_name')
                    ->label('User')
                    ->description(fn (Payment $record): string => (string) $record->user_email)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('top_up_count')
                    ->label('Orders')
                    ->alignEnd()
                    ->badge()
                    ->color('info')
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
                    ->formatStateUsing(fn (mixed $state): string => $this->formatCurrency((float) $state)),
                TextColumn::make('last_top_up_at')
                    ->label('Last Top-Up')
                    ->dateTime('Y-m-d H:i')
                    ->since(),
            ])
            ->stackedOnMobile();
    }

    private function formatCurrency(float $amount): string
    {
        return number_format($amount, 2).' USD';
    }
}
