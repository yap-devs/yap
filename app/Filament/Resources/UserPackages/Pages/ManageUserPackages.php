<?php

namespace App\Filament\Resources\UserPackages\Pages;

use App\Filament\Resources\UserPackages\UserPackageResource;
use Filament\Resources\Pages\ManageRecords;

class ManageUserPackages extends ManageRecords
{
    protected static string $resource = UserPackageResource::class;

    public function getSubheading(): ?string
    {
        return 'Review package usage, availability and queue order across all accounts.';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
