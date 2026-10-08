<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\UserResource;
use App\Filament\Widgets\Concerns\InteractsWithDashboardControls;
use App\Models\UserPackage;
use App\Services\AdminDashboardReportService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class AtRiskPackagesTable extends TableWidget
{
    use InteractsWithDashboardControls;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Active packages under 10% remaining')
            ->poll(fn (): ?string => $this->getPollingInterval())
            ->query(UserPackage::query()
                ->with(['user', 'package'])
                ->available()
                ->where('user_id', '>', AdminDashboardReportService::REPORTABLE_USER_ID_THRESHOLD)
                ->whereHas('package', fn (Builder $query): Builder => $query
                    ->whereRaw('user_packages.remaining_traffic * 10 < CASE WHEN packages.traffic_limit > 1 THEN packages.traffic_limit ELSE 1 END')))
            ->defaultSort('remaining_traffic')
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25, 50])
            ->columns([
                TextColumn::make('user.name')
                    ->label('User')
                    ->description(fn (UserPackage $record): string => $record->user?->email ?? 'Unknown user')
                    ->url(fn (UserPackage $record): string => UserResource::getUrl('view', ['record' => $record->user_id])),
                TextColumn::make('package.name')
                    ->label('Package'),
                TextColumn::make('remaining_traffic')
                    ->label('Remaining')
                    ->formatStateUsing(fn (mixed $state): string => number_format((float) $state / 1073741824, 2).' GB')
                    ->alignEnd(),
                TextColumn::make('ended_at')
                    ->label('Ends')
                    ->dateTime('Y-m-d H:i'),
            ])
            ->stackedOnMobile();
    }
}
