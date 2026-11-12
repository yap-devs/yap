<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use Filament\Resources\Pages\ManageRecords;

class ManageTickets extends ManageRecords
{
    protected static string $resource = TicketResource::class;

    public function getSubheading(): ?string
    {
        return 'Review customer requests and continue the conversation.';
    }
}
