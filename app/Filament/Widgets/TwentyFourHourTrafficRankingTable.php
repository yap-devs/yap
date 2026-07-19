<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardControls;
use App\Models\UserStat;
use App\Services\AdminDashboardReportService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class TwentyFourHourTrafficRankingTable extends TableWidget
{
    use InteractsWithDashboardControls;

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('24-Hour User Traffic Ranking')
            ->description('Users ranked by combined downlink and uplink traffic in the rolling window.')
            ->query(app(AdminDashboardReportService::class)->getLastTwentyFourHourTrafficRankingQuery()->reorder())
            ->defaultSort('total_traffic_bytes', 'desc')
            ->defaultPaginationPageOption(25)
            ->paginationPageOptions([10, 25, 50, 100])
            ->striped()
            ->emptyStateHeading('No traffic recorded')
            ->emptyStateDescription('No reportable user traffic exists in the latest 24-hour window.')
            ->columns([
                TextColumn::make('rank')
                    ->label('#')
                    ->rowIndex(),
                TextColumn::make('user_name')
                    ->label('User')
                    ->description(fn (UserStat $record): string => $record->user_email.' | User #'.$record->user_id)
                    ->wrap()
                    ->searchable(['users.name', 'users.email']),
                TextColumn::make('total_traffic_bytes')
                    ->label('Total Traffic')
                    ->alignEnd()
                    ->badge()
                    ->color('primary')
                    ->sortable()
                    ->formatStateUsing(fn (mixed $state): string => $this->formatGigabytes((float) $state)),
                TextColumn::make('traffic_downlink_bytes')
                    ->label('Downlink')
                    ->alignEnd()
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->formatStateUsing(fn (mixed $state): string => $this->formatGigabytes((float) $state)),
                TextColumn::make('traffic_uplink_bytes')
                    ->label('Uplink')
                    ->alignEnd()
                    ->badge()
                    ->color('success')
                    ->sortable()
                    ->formatStateUsing(fn (mixed $state): string => $this->formatGigabytes((float) $state)),
                TextColumn::make('last_activity_at')
                    ->label('Last Activity')
                    ->dateTime('Y-m-d H:i')
                    ->since()
                    ->sortable(),
            ])
            ->stackedOnMobile();
    }

    private function formatGigabytes(float $bytes): string
    {
        return number_format($bytes / 1024 / 1024 / 1024, 2).' GB';
    }
}
