<?php

namespace App\Models;

use App\SerializeDate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AffiliatePromoter extends Model
{
    use HasFactory, SerializeDate, SoftDeletes;

    const STATUS_ACTIVE = 'active';

    const STATUS_BLOCKED = 'blocked';

    protected $fillable = [
        'user_id',
        'code',
        'status',
        'custom_commission_rate',
        'total_valid_referrals',
        'total_commission_amount',
    ];

    protected function casts(): array
    {
        return [
            'custom_commission_rate' => 'decimal:4',
            'total_commission_amount' => 'decimal:2',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function referrals()
    {
        return $this->hasMany(AffiliateReferral::class, 'promoter_id');
    }

    public function commissions()
    {
        return $this->hasMany(AffiliateCommission::class, 'promoter_id');
    }
}
