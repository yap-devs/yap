<?php

namespace App\Filament\Widgets;

use App\Models\Sub2apiUsageRecord;
use App\Services\AdminDashboardReportService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class AiRecentUsageTable extends TableWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '30s';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recent AI Requests')
            ->description('Latest individual AI API calls across all users.')
            ->query(app(AdminDashboardReportService::class)->getAiRecentUsageQuery())
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25, 50])
            ->striped()
            ->columns([
                TextColumn::make('user_name')
                    ->label('User')
                    ->description(fn (Sub2apiUsageRecord $record): string => $record->user_email ?? '')
                    ->searchable(),
                TextColumn::make('model')
                    ->label('Model')
                    ->badge()
                    ->color('info'),
                TextColumn::make('amount')
                    ->label('Cost')
                    ->alignEnd()
                    ->formatStateUsing(fn (mixed $state): string => '$'.number_format((float) $state, 6))
                    ->sortable(),
                TextColumn::make('usage_created_at')
                    ->label('Time')
                    ->dateTime()
                    ->sortable(),
            ]);
    }
}
