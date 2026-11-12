<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use SoftDeletes;

    const STATUS_OPEN = 'open';

    const STATUS_CLOSED = 'closed';

    public function scopeNeedsReply(Builder $query): void
    {
        $query->where('status', self::STATUS_OPEN)
            ->whereDoesntHave('messages', fn (Builder $messages) => $messages
                ->where('is_admin', true)
                ->where('id', TicketMessage::select('id')
                    ->whereColumn('ticket_id', 'tickets.id')
                    ->orderByDesc('id')
                    ->limit(1)));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class)->orderBy('id');
    }
}
