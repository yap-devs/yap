<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\UserResource;
use App\Filament\Widgets\Concerns\InteractsWithDashboardControls;
use App\Models\User;
use App\Services\AdminDashboardReportService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class AtRiskUsersTable extends TableWidget
{
    use InteractsWithDashboardControls;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Low-balance users without an available package')
            ->poll(fn (): ?string => $this->getPollingInterval())
            ->query(User::query()
                ->where('id', '>', AdminDashboardReportService::REPORTABLE_USER_ID_THRESHOLD)
                ->where('balance', '<', 1)
                ->whereDoesntHave('packages', fn (Builder $query): Builder => $query->available()))
            ->defaultSort('balance')
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25, 50])
            ->columns([
                TextColumn::make('name')
                    ->label('User')
                    ->description(fn (User $record): string => $record->email)
                    ->searchable()
                    ->url(fn (User $record): string => UserResource::getUrl('edit', ['record' => $record])),
                TextColumn::make('balance')
                    ->label('Balance')
                    ->money('USD')
                    ->sortable()
                    ->color(fn (float $state): string => $state < 0 ? 'danger' : 'warning'),
                TextColumn::make('traffic_unpaid')
                    ->label('Unbilled Traffic')
                    ->formatStateUsing(fn (mixed $state): string => number_format((float) $state / 1073741824, 2).' GB')
                    ->alignEnd(),
            ])
            ->stackedOnMobile();
    }
}
