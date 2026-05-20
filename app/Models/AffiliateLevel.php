<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AffiliateLevel extends Model
{
    use HasFactory, SoftDeletes;

    const STATUS_ACTIVE = 'active';

    const STATUS_DISABLED = 'disabled';

    protected $fillable = [
        'level',
        'name',
        'minimum_self_paid_amount',
        'minimum_valid_referrals',
        'commission_rate',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'minimum_self_paid_amount' => 'decimal:2',
            'commission_rate' => 'decimal:4',
        ];
    }
}
