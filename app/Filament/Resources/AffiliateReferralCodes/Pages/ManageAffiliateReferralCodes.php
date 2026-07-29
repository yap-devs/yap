<?php

namespace App\Filament\Resources\AffiliateReferralCodes\Pages;

use App\Filament\Resources\AffiliateReferralCodes\AffiliateReferralCodeResource;
use Filament\Resources\Pages\ManageRecords;

class ManageAffiliateReferralCodes extends ManageRecords
{
    protected static string $resource = AffiliateReferralCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
