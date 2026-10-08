<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\UserResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    public function getTitle(): string
    {
        return 'Recharge order #'.$this->getRecord()->id;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('account')->label('Account details')->icon('heroicon-o-user')
                ->visible(fn (): bool => $this->record->user !== null)
                ->url(fn (): ?string => $this->record->user ? UserResource::getUrl('view', ['record' => $this->record->user_id]) : null),
        ];
    }
}
