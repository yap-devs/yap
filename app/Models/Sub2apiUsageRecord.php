<?php

namespace App\Models;

use App\SerializeDate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sub2apiUsageRecord extends Model
{
    use HasFactory, SerializeDate, SoftDeletes;

    protected $fillable = [
        'user_id',
        'remote_usage_id',
        'remote_request_id',
        'remote_api_key_id',
        'model',
        'amount',
        'usage_created_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'usage_created_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
