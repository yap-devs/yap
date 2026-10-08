<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\UserResource;
use App\Filament\Widgets\Concerns\InteractsWithDashboardControls;
use App\Models\Sub2apiUsageRecord;
use App\Services\AdminDashboardReportService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class AiUsageRankingTable extends TableWidget
{
    protected static bool $isLazy = false;

    use InteractsWithDashboardControls;

    protected int|string|array $columnSpan = [
        'md' => 6,
        'xl' => 6,
    ];

    public function table(Table $table): Table
    {
        return $table
            ->heading('Today AI Usage Ranking')
            ->description('Top AI spenders today by total cost.')
            ->query(app(AdminDashboardReportService::class)->getAiUsageRankingQuery()->reorder())
            ->defaultSort('total_cost', 'desc')
            ->defaultPaginationPageOption(8)
            ->paginationPageOptions([8, 16, 32])
            ->defaultKeySort(false)
            ->striped()
            ->columns([
                TextColumn::make('rank')
                    ->label('#')
                    ->rowIndex(),
                TextColumn::make('user_name')->url(fn (Sub2apiUsageRecord $record): string => UserResource::getUrl('view', ['record' => $record->user_id]))
                    ->label('User')
                    ->description(fn (Sub2apiUsageRecord $record): string => $record->user_email)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('request_count')
                    ->label('Requests')
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('total_cost')
                    ->label('Cost')
                    ->alignEnd()
                    ->badge()
                    ->color(fn (mixed $state): string => $this->getCostTone((float) $state))
                    ->sortable()
                    ->formatStateUsing(fn (mixed $state): string => '$'.number_format((float) $state, 2)),
            ])
            ->stackedOnMobile();
    }

    private function getCostTone(float $cost): string
    {
        return match (true) {
            $cost >= 5 => 'danger',
            $cost >= 1 => 'warning',
            default => 'success',
        };
    }
}
