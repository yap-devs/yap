<?php

namespace App\Filament\Resources\VmessServerResource\Pages;

use App\Filament\Resources\VmessServerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVmessServer extends EditRecord
{
    protected static string $resource = VmessServerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
