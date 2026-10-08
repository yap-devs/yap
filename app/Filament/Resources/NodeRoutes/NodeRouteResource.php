<?php

namespace App\Filament\Resources\NodeRoutes;

use App\Filament\Resources\NodeRoutes\Pages\ManageNodeRoutes;
use App\Models\NodeRoute;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class NodeRouteResource extends Resource
{
    protected static ?string $model = NodeRoute::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Network';

    public static function canAccess(): bool
    {
        return auth()->id() === 1 && (bool) config('node_agent.enabled');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('node_id')->disabled(fn (?NodeRoute $record): bool => $record !== null)->relationship('node', 'name')->required()->searchable()->preload(),
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('server')->required()->maxLength(255),
            TextInput::make('port')->required()->integer()->minValue(1)->maxValue(65535),
            TextInput::make('inbound_tag')->disabled(fn (?NodeRoute $record): bool => $record !== null)->required()->default('yap-vmess')->maxLength(64)->rules(['regex:/^yap-[a-zA-Z0-9_-]+$/', 'not_in:yap-api']),
            TextInput::make('listen_port')->disabled(fn (?NodeRoute $record): bool => $record !== null)->required()->integer()->minValue(1)->maxValue(65535),
            TextInput::make('rate')->required()->numeric()->minValue(0)->maxValue(999999.99)->rules(['decimal:0,2'])->default('1.00'),
            TextInput::make('sort')->integer()->minValue(0)->default(0),
            Toggle::make('enabled')->default(false),
            Toggle::make('for_low_priority')->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->stackedOnMobile()->columns([TextColumn::make('name')->searchable(), TextColumn::make('node.name'), TextColumn::make('server'), TextColumn::make('port'), TextColumn::make('listen_port'), TextColumn::make('rate'), IconColumn::make('enabled')->boolean()])
            ->defaultSort('id', 'desc')
            ->recordActions([EditAction::make()]);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ManageNodeRoutes::route('/')];
    }
}
