<?php

namespace App\Filament\Resources\RelayServerResource\Pages;

use App\Filament\Resources\RelayServerResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditRelayServer extends EditRecord
{
    protected static string $resource = RelayServerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
