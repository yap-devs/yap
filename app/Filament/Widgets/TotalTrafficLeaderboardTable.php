<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardControls;
use App\Models\User;
use App\Services\AdminDashboardReportService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class TotalTrafficLeaderboardTable extends TableWidget
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
            ->heading('User Total Traffic Report')
            ->description('Lifetime traffic leaderboard for all reportable users.')
            ->query(app(AdminDashboardReportService::class)->getTotalTrafficLeaderboardQuery())
            ->poll(fn (): ?string => $this->getPollingInterval())
            ->defaultPaginationPageOption(8)
            ->paginationPageOptions([8, 16, 32])
            ->striped()
            ->recordClasses(fn (User $record): array => [
                'bg-rose-50/70 dark:bg-rose-950/20' => (float) $record->balance < 0,
                'bg-amber-50/70 dark:bg-amber-950/20' => (float) $record->balance >= 0 && (float) $record->balance < 1,
            ])
            ->columns([
                TextColumn::make('rank')
                    ->label('#')
                    ->rowIndex(),
                TextColumn::make('name')
                    ->description(fn (User $record): string => $record->is_valid ? 'Access healthy' : 'Needs attention')
                    ->searchable(),
                TextColumn::make('balance')
                    ->label('Balance')
                    ->alignEnd()
                    ->badge()
                    ->color(fn (mixed $state): string => $this->getBalanceTone((float) $state))
                    ->formatStateUsing(fn (mixed $state): string => $this->formatCurrency((float) $state)),
                TextColumn::make('total_traffic_bytes')
                    ->label('Traffic')
                    ->alignEnd()
                    ->badge()
                    ->color(fn (mixed $state): string => $this->getLifetimeTrafficTone((float) $state))
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

    private function getBalanceTone(float $amount): string
    {
        return match (true) {
            $amount < 0 => 'danger',
            $amount < 1 => 'warning',
            default => 'success',
        };
    }

    private function getLifetimeTrafficTone(float $bytes): string
    {
        $gigabytes = $bytes / 1024 / 1024 / 1024;

        return match (true) {
            $gigabytes >= 500 => 'danger',
            $gigabytes >= 100 => 'warning',
            default => 'info',
        };
    }
}
