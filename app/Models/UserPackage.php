<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserPackage extends Model
{
    use HasFactory, SoftDeletes;

    const STATUS_ACTIVE = 'active';

    const STATUS_EXPIRED = 'expired';

    const STATUS_USED = 'used';

    const STATUS_DISABLED = 'disabled';

    const DISPLAY_STATUS_QUEUED = 'queued';

    protected $with = ['package'];

    protected $fillable = [
        'user_id',
        'package_id',
        'remaining_traffic',
        'priority',
        'status',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where($query->getModel()->qualifyColumn('status'), self::STATUS_ACTIVE);
    }

    public function scopeStarted(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereNull($query->getModel()->qualifyColumn('started_at'))
                ->orWhere($query->getModel()->qualifyColumn('started_at'), '<=', now());
        });
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->active()->started();
    }

    public function scopeQueued(Builder $query): Builder
    {
        return $query->active()
            ->where($query->getModel()->qualifyColumn('started_at'), '>', now());
    }

    public function isStarted(): bool
    {
        return $this->started_at === null
            || CarbonImmutable::parse($this->started_at)->lessThanOrEqualTo(now());
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->isStarted();
    }

    public function isQueued(): bool
    {
        return $this->status === self::STATUS_ACTIVE && ! $this->isStarted();
    }

    public function displayStatus(): string
    {
        return $this->isQueued()
            ? self::DISPLAY_STATUS_QUEUED
            : $this->status;
    }

    public function activateAt(CarbonImmutable $started_at): void
    {
        $duration_days = $this->package->duration_days ?? 0;

        $this->started_at = $started_at;
        $this->ended_at = $duration_days > 0
            ? $started_at->addDays($duration_days)
            : null;
    }
}
