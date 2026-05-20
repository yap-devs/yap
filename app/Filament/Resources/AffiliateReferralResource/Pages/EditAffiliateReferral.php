<?php

namespace App\Filament\Resources\AffiliateReferralResource\Pages;

use App\Filament\Resources\AffiliateReferralResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditAffiliateReferral extends EditRecord
{
    protected static string $resource = AffiliateReferralResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make(), ForceDeleteAction::make(), RestoreAction::make()];
    }
}
