<?php

namespace App\Filament\Widgets;

use App\Services\AdminDashboardReportService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class ActiveUserPackagesTable extends TableWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Active User Packages')
            ->description('Current active subscriptions with remaining traffic and expiration windows.')
            ->query(app(AdminDashboardReportService::class)->getActiveUserPackagesQuery())
            ->poll('30s')
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25, 50])
            ->striped()
            ->columns([
                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable(),
                TextColumn::make('package.name')
                    ->label('Package')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color('success'),
                TextColumn::make('started_at')
                    ->label('Started')
                    ->dateTime('Y-m-d H:i'),
                TextColumn::make('ended_at')
                    ->label('Ends')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('remaining_traffic')
                    ->label('Remaining')
                    ->alignEnd()
                    ->formatStateUsing(fn (mixed $state): string => $this->formatGigabytes((float) $state)),
                TextColumn::make('package.traffic_limit')
                    ->label('Total')
                    ->alignEnd()
                    ->formatStateUsing(fn (mixed $state): string => $this->formatGigabytes((float) $state)),
            ]);
    }

    private function formatGigabytes(float $bytes): string
    {
        return number_format($bytes / 1024 / 1024 / 1024, 2).' GB';
    }
}
