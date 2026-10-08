<?php

namespace App\Filament\Resources\TrafficRecords;

use App\Filament\Resources\TrafficRecords\Pages\ManageTrafficRecords;
use App\Models\TrafficRecord;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class TrafficRecordResource extends Resource
{
    protected static ?string $model = TrafficRecord::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Network';

    public static function canAccess(): bool
    {
        return auth()->id() === 1 && (bool) config('node_agent.enabled');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('batch.node.name'), TextColumn::make('user_id')->searchable(), TextColumn::make('route.name'), TextColumn::make('raw_uplink'), TextColumn::make('raw_downlink'), TextColumn::make('applied_rate'), TextColumn::make('billed_uplink'), TextColumn::make('billed_downlink'), TextColumn::make('created_at')->dateTime()->sortable()])
            ->defaultSort('id', 'desc')
            ->recordActions([]);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ManageTrafficRecords::route('/')];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }
}
