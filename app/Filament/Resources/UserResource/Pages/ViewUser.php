<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->name;
    }

    #[On('account-operation-completed.{record.id}')]
    public function refreshAccount(): void
    {
        $this->getRecord()->refresh();
    }

    protected function getHeaderActions(): array
    {
        return [
            UserResource::adjustBalanceAction(),
            EditAction::make()->label('Edit basic details'),
        ];
    }
}
