<?php

namespace App\Filament\Resources\RelayServerResource\Pages;

use App\Filament\Resources\RelayServerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRelayServer extends EditRecord
{
    protected static string $resource = RelayServerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
