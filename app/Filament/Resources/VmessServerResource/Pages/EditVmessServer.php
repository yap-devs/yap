<?php

namespace App\Filament\Resources\VmessServerResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use App\Filament\Resources\VmessServerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVmessServer extends EditRecord
{
    protected static string $resource = VmessServerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
