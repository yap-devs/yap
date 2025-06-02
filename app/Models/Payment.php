<?php

namespace App\Models;

use App\SerializeDate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes, SerializeDate;

    const STATUS_CREATED = 'created';
    const STATUS_PAID = 'paid';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_EXPIRED = 'expired';
    const STATUS_REFUNDED = 'refunded';

    const GATEWAY_GITHUB = 'github';
    const GATEWAY_ALIPAY = 'alipay';
    const GATEWAY_USDT = 'usdt';

    protected $fillable = [
        'user_id',
        'gateway',
        'status',
        'amount',
        'remote_id',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
