<?php

namespace App\Filament\Resources\AffiliateReferralResource\Pages;

use App\Filament\Resources\AffiliateReferralResource;
use Filament\Resources\Pages\ViewRecord;

class ViewAffiliateReferral extends ViewRecord
{
    protected static string $resource = AffiliateReferralResource::class;

    public function getTitle(): string
    {
        return 'Referral #'.$this->record->id;
    }

    protected function getHeaderActions(): array
    {
        return [AffiliateReferralResource::statusAction()];
    }
}
