<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Ticket;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    protected string $view = 'filament.resources.tickets.pages.view-ticket';

    public string $reply = '';

    public function getTitle(): string
    {
        return $this->getRecord()->subject;
    }

    public function getSubheading(): ?string
    {
        return 'Ticket #'.$this->getRecord()->id;
    }

    public function getConversationMessages(): Collection
    {
        return $this->getRecord()->messages()->get();
    }

    public function sendReply(): void
    {
        abort_unless(TicketResource::canAccess(), 403);
        $data = $this->validate(['reply' => ['required', 'string', 'max:10000']]);
        DB::transaction(function () use ($data): void {
            $ticket = Ticket::whereKey($this->getRecord()->id)->lockForUpdate()->firstOrFail();
            if ($ticket->status !== Ticket::STATUS_OPEN) {
                throw ValidationException::withMessages(['reply' => 'This ticket is closed.']);
            }
            $ticket->messages()->create(['user_id' => auth()->id(), 'is_admin' => true, 'body' => $data['reply']]);
            $ticket->touch();
        });
        $this->reset('reply');
        $this->getRecord()->refresh();
        Notification::make()->title('Reply sent')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('close')->label('Close ticket')->color('gray')->icon('heroicon-o-check-circle')
                ->requiresConfirmation()->modalDescription('The customer will no longer be able to reply to this ticket.')
                ->visible(fn (): bool => $this->getRecord()->status === Ticket::STATUS_OPEN)
                ->action(function (): void {
                    abort_unless(TicketResource::canAccess(), 403);
                    DB::transaction(function (): void {
                        $ticket = Ticket::whereKey($this->getRecord()->id)->lockForUpdate()->firstOrFail();
                        if ($ticket->status === Ticket::STATUS_OPEN) {
                            $ticket->update(['status' => Ticket::STATUS_CLOSED]);
                        }
                    });
                    $this->getRecord()->refresh();
                    Notification::make()->title('Ticket closed')->success()->send();
                }),
            Action::make('reopen')->label('Reopen ticket')->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()->modalDescription('The customer will be able to reply to this ticket again.')
                ->visible(fn (): bool => $this->getRecord()->status === Ticket::STATUS_CLOSED)
                ->action(function (): void {
                    abort_unless(TicketResource::canAccess(), 403);
                    DB::transaction(function (): void {
                        $ticket = Ticket::whereKey($this->getRecord()->id)->lockForUpdate()->firstOrFail();
                        if ($ticket->status === Ticket::STATUS_CLOSED) {
                            $ticket->update(['status' => Ticket::STATUS_OPEN]);
                        }
                    });
                    $this->getRecord()->refresh();
                    Notification::make()->title('Ticket reopened')->success()->send();
                }),
        ];
    }
}
