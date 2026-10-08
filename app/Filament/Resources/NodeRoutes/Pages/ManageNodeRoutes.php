<?php

namespace App\Filament\Resources\NodeRoutes\Pages;

use App\Filament\Resources\NodeRoutes\NodeRouteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageNodeRoutes extends ManageRecords
{
    protected static string $resource = NodeRouteResource::class;

    public function getSubheading(): ?string
    {
        return 'Public subscription entries, billing multipliers, and low-balance permissions. A route is available only while its node is enabled.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Create route')];
    }
}
