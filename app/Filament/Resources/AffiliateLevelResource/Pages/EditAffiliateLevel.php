<?php

namespace App\Filament\Resources\AffiliateLevelResource\Pages;

use App\Filament\Resources\AffiliateLevelResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditAffiliateLevel extends EditRecord
{
    protected static string $resource = AffiliateLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make(), ForceDeleteAction::make(), RestoreAction::make()];
    }
}
