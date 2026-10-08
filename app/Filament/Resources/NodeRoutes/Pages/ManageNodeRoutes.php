<?php

namespace App\Filament\Resources\NodeRoutes\Pages;

use App\Filament\Resources\NodeRoutes\NodeRouteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageNodeRoutes extends ManageRecords
{
    protected static string $resource = NodeRouteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
