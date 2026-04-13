<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VmessServerResource\Pages\CreateVmessServer;
use App\Filament\Resources\VmessServerResource\Pages\EditVmessServer;
use App\Filament\Resources\VmessServerResource\Pages\ListVmessServers;
use App\Models\VmessServer;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class VmessServerResource extends Resource
{
    protected static ?string $model = VmessServer::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('server')
                    ->maxLength(255),
                TextInput::make('port')
                    ->required()
                    ->numeric(),
                TextInput::make('rate')
                    ->required()
                    ->numeric()
                    ->default(1.0),
                TextInput::make('internal_server')
                    ->required()
                    ->maxLength(255)
                    ->default(''),
                Toggle::make('enabled')
                    ->required(),
                Toggle::make('for_low_priority')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('relays_count')->counts('relays'),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('server')
                    ->searchable(),
                TextColumn::make('port')
                    ->numeric(thousandsSeparator: false)
                    ->sortable(),
                TextColumn::make('rate')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('internal_server')
                    ->searchable(),
                ToggleColumn::make('enabled'),
                ToggleColumn::make('for_low_priority'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVmessServers::route('/'),
            'create' => CreateVmessServer::route('/create'),
            'edit' => EditVmessServer::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
