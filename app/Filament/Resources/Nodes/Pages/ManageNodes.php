<?php

namespace App\Filament\Resources\Nodes\Pages;

use App\Filament\Resources\Nodes\NodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageNodes extends ManageRecords
{
    protected static string $resource = NodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
