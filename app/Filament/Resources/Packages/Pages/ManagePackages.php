<?php

namespace App\Filament\Resources\Packages\Pages;

use App\Filament\Resources\Packages\PackageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManagePackages extends ManageRecords
{
    protected static string $resource = PackageResource::class;

    public function getSubheading(): ?string
    {
        return 'Manage package names, prices, traffic allowances and durations.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Create product')];
    }
}
