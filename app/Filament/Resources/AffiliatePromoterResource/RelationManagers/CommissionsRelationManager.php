<?php

namespace App\Filament\Resources\AffiliatePromoterResource\RelationManagers;

use App\Filament\Resources\AffiliateCommissionResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class CommissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'commissions';

    public function infolist(Schema $schema): Schema
    {
        return AffiliateCommissionResource::infolist($schema);
    }

    public function table(Table $table): Table
    {
        return AffiliateCommissionResource::table($table)->modifyQueryUsing(fn ($query) => $query->with(['referrer', 'referred']));
    }
}
