<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrafficBatch extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['node_id', 'batch_uuid', 'payload_hash', 'received_at', 'aggregated_at'];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'aggregated_at' => 'datetime',
        ];
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(TrafficRecord::class);
    }
}
