<?php

namespace App\Filament\Resources\VmessServerResource\Pages;

use App\Filament\Resources\VmessServerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVmessServers extends ListRecords
{
    protected static string $resource = VmessServerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
