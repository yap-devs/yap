<?php

namespace App\Filament\Resources\AffiliatePromoterResource\Pages;

use App\Filament\Resources\AffiliatePromoterResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditAffiliatePromoter extends EditRecord
{
    protected static string $resource = AffiliatePromoterResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make(), ForceDeleteAction::make(), RestoreAction::make()];
    }
}
