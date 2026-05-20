<?php

namespace App\Filament\Resources\RelayServerResource\Pages;

use App\Filament\Resources\RelayServerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRelayServers extends ListRecords
{
    protected static string $resource = RelayServerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
