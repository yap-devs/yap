<?php

namespace App\Filament\Resources\AffiliateCommissionResource\Pages;

use App\Filament\Resources\AffiliateCommissionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditAffiliateCommission extends EditRecord
{
    protected static string $resource = AffiliateCommissionResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make(), ForceDeleteAction::make(), RestoreAction::make()];
    }
}
