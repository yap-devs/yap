<?php

namespace App\Filament\Resources\AffiliateLevelResource\Pages;

use App\Filament\Resources\AffiliateLevelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAffiliateLevels extends ListRecords
{
    protected static string $resource = AffiliateLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
