<?php

namespace App\Filament\Resources\AffiliatePromoterResource\RelationManagers;

use App\Filament\Resources\AffiliateReferralResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class ReferralsRelationManager extends RelationManager
{
    protected static string $relationship = 'referrals';

    public function table(Table $table): Table
    {
        return AffiliateReferralResource::table($table)->modifyQueryUsing(fn ($query) => $query->with(['referrer', 'referred']))->recordActions([]);
    }
}
