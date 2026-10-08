<?php

namespace App\Filament\Resources\Nodes\Pages;

use App\Filament\Resources\Nodes\NodeResource;
use App\Filament\Resources\TrafficRecords\TrafficRecordResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;

class ViewNode extends ViewRecord
{
    protected static string $resource = NodeResource::class;

    public function getTitle(): string
    {
        return $this->record->name;
    }

    public function hydrate(): void
    {
        parent::hydrate();
        $this->refreshNode();
    }

    #[On('node-operation-completed.{record.id}')]
    public function refreshNode(): void
    {
        $this->record = NodeResource::getEloquentQuery()->findOrFail($this->record->id);
    }

    protected function getHeaderActions(): array
    {
        return [NodeResource::editAction(), Action::make('traffic')->label('Traffic records')->url(fn (): string => TrafficRecordResource::getUrl('index', ['tableFilters' => ['node_id' => ['value' => $this->record->id]]]))];
    }
}
