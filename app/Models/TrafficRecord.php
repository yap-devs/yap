<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TrafficRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['traffic_batch_id', 'user_id', 'node_route_id', 'raw_uplink', 'raw_downlink', 'applied_rate', 'billed_uplink', 'billed_downlink'];

    protected function casts(): array
    {
        return [
            'applied_rate' => 'decimal:2',
            'raw_uplink' => 'integer',
            'raw_downlink' => 'integer',
            'billed_uplink' => 'integer',
            'billed_downlink' => 'integer',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(TrafficBatch::class, 'traffic_batch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(NodeRoute::class, 'node_route_id');
    }
}
