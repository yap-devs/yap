<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\UserResource;
use App\Filament\Widgets\Concerns\InteractsWithDashboardControls;
use App\Models\Sub2apiUsageRecord;
use App\Services\AdminDashboardReportService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class AiRecentUsageTable extends TableWidget
{
    protected static bool $isLazy = false;

    use InteractsWithDashboardControls;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recent AI Requests')
            ->description('Latest individual AI API calls across all users.')
            ->query(app(AdminDashboardReportService::class)->getAiRecentUsageQuery()->reorder())
            ->defaultSort('sub2api_usage_records.id', 'desc')
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25, 50])
            ->striped()
            ->columns([
                TextColumn::make('user_name')->url(fn (Sub2apiUsageRecord $record): string => UserResource::getUrl('view', ['record' => $record->user_id]))
                    ->label('User')
                    ->description(fn (Sub2apiUsageRecord $record): string => $record->user_email ?? '')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('model')
                    ->label('Model')
                    ->wrap()
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
            ])
            ->stackedOnMobile();
    }
}
