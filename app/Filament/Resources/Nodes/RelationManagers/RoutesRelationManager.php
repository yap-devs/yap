<?php

namespace App\Filament\Resources\Nodes\RelationManagers;

use App\Filament\Resources\NodeRoutes\NodeRouteResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class RoutesRelationManager extends RelationManager
{
    protected static string $relationship = 'routes';

    protected static bool $isLazy = false;

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return NodeRouteResource::form($schema);
    }

    public function table(Table $table): Table
    {
        return NodeRouteResource::table($table)->modifyQueryUsing(fn ($query) => $query->with('node'));
    }
}
