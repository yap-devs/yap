<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardControls;
use App\Services\AdminDashboardReportService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class DailyTrafficRankingTable extends TableWidget
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
            ->heading('Today / Yesterday Traffic')
            ->description('Top traffic consumers from the latest two daily windows.')
            ->query(app(AdminDashboardReportService::class)->getDailyTrafficRankingQuery())
            ->poll(fn (): ?string => $this->getPollingInterval())
            ->defaultPaginationPageOption(8)
            ->paginationPageOptions([8, 16, 32])
            ->striped()
            ->columns([
                TextColumn::make('day')
                    ->label('Day')
                    ->badge()
                    ->sortable(),
                TextColumn::make('user_name')
                    ->label('User')
                    ->searchable(),
                TextColumn::make('daily_traffic_bytes')
                    ->label('Traffic')
                    ->alignEnd()
                    ->sortable()
                    ->formatStateUsing(fn (mixed $state): string => $this->formatGigabytes((float) $state)),
            ]);
    }

    private function formatGigabytes(float $bytes): string
    {
        return number_format($bytes / 1024 / 1024 / 1024, 2).' GB';
    }
}
