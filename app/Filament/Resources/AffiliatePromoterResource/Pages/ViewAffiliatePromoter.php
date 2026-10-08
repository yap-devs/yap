<?php

namespace App\Filament\Resources\AffiliatePromoterResource\Pages;

use App\Filament\Resources\AffiliatePromoterResource;
use App\Filament\Resources\AffiliateReferralCodes\AffiliateReferralCodeResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;

class ViewAffiliatePromoter extends ViewRecord
{
    protected static string $resource = AffiliatePromoterResource::class;

    public function getTitle(): string
    {
        return $this->record->user?->email ?? 'Promoter #'.$this->record->id;
    }

    public function hydrate(): void
    {
        parent::hydrate();
        $this->refreshPromoter();
    }

    #[On('promoter-operation-completed.{record.id}')]
    public function refreshPromoter(): void
    {
        $this->record = AffiliatePromoterResource::getEloquentQuery()->findOrFail($this->record->id);
    }

    protected function getHeaderActions(): array
    {
        return [AffiliatePromoterResource::configureAction(), Action::make('codes')->label('Referral codes')->url(fn (): string => AffiliateReferralCodeResource::getUrl('index', ['tableSearch' => $this->record->user?->email]))];
    }
}
