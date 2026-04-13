<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardControls;
use App\Services\AdminDashboardReportService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class TotalTrafficLeaderboardTable extends TableWidget
{
    use InteractsWithDashboardControls;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = [
        'md' => 4,
        'xl' => 4,
    ];

    public function table(Table $table): Table
    {
        return $table
            ->heading('User Total Traffic Report')
            ->description('Lifetime traffic leaderboard for all reportable users.')
            ->query(app(AdminDashboardReportService::class)->getTotalTrafficLeaderboardQuery())
            ->poll(fn (): ?string => $this->getPollingInterval())
            ->defaultPaginationPageOption(8)
            ->paginationPageOptions([8, 16, 32])
            ->striped()
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('balance')
                    ->label('Balance')
                    ->alignEnd()
                    ->formatStateUsing(fn (mixed $state): string => $this->formatCurrency((float) $state)),
                TextColumn::make('total_traffic_bytes')
                    ->label('Traffic')
                    ->alignEnd()
                    ->sortable()
                    ->formatStateUsing(fn (mixed $state): string => $this->formatGigabytes((float) $state)),
            ]);
    }

    private function formatCurrency(float $amount): string
    {
        return number_format($amount, 2).' USD';
    }

    private function formatGigabytes(float $bytes): string
    {
        return number_format($bytes / 1024 / 1024 / 1024, 2).' GB';
    }
}
