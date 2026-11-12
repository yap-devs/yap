<?php

namespace App\Filament\Resources\Tickets;

use App\Filament\Resources\Tickets\Pages\ManageTickets;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Models\Ticket;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static string|\UnitEnum|null $navigationGroup = 'Customers';

    protected static ?string $navigationLabel = 'Support tickets';

    protected static ?string $recordTitleAttribute = 'subject';

    public static function canAccess(): bool
    {
        return auth()->id() === 1;
    }

    public static function canView(Model $record): bool
    {
        return static::canAccess();
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('id')->label('#')->sortable(),
            TextColumn::make('subject')->label('Subject')->searchable()->weight('medium')->wrap(),
            TextColumn::make('user.name')->label('Customer')->searchable()->description(fn (Ticket $record): ?string => $record->user?->email),
            TextColumn::make('status')->badge()->formatStateUsing(fn (string $state): string => $state === Ticket::STATUS_OPEN ? 'Open' : 'Closed')
                ->color(fn (string $state): string => $state === Ticket::STATUS_OPEN ? 'success' : 'gray'),
            TextColumn::make('messages_count')->counts('messages')->label('Messages'),
            TextColumn::make('updated_at')->label('Last activity')->since()->dateTimeTooltip()->sortable(),
        ])->defaultSort('updated_at', 'desc')->stackedOnMobile()->filters([
            Filter::make('needs_reply')->label('Needs reply')->query(fn (Builder $query): Builder => $query->needsReply()),
            SelectFilter::make('status')->options([Ticket::STATUS_OPEN => 'Open', Ticket::STATUS_CLOSED => 'Closed']),
        ])->recordUrl(fn (Ticket $record): string => static::getUrl('view', ['record' => $record]))
            ->recordActions([ViewAction::make()->label('Open ticket')]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageTickets::route('/'), 'view' => ViewTicket::route('/{record}')];
    }
}
