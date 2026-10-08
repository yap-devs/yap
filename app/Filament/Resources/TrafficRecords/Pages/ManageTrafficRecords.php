<?php

namespace App\Filament\Resources\TrafficRecords\Pages;

use App\Filament\Resources\TrafficRecords\TrafficRecordResource;
use Filament\Resources\Pages\ManageRecords;

class ManageTrafficRecords extends ManageRecords
{
    protected static string $resource = TrafficRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
