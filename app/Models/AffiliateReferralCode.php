<?php

namespace App\Models;

use App\SerializeDate;
use Database\Factories\AffiliateReferralCodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AffiliateReferralCode extends Model
{
    /** @use HasFactory<AffiliateReferralCodeFactory> */
    use HasFactory, SerializeDate, SoftDeletes;

    const STATUS_ACTIVE = 'active';

    const STATUS_DISABLED = 'disabled';

    const TYPE_CUSTOM = 'custom';

    const TYPE_SYSTEM = 'system';

    protected $fillable = [
        'promoter_id',
        'code',
        'type',
        'status',
        'disabled_at',
    ];

    protected $attributes = [
        'type' => self::TYPE_CUSTOM,
        'status' => self::STATUS_ACTIVE,
    ];

    protected function casts(): array
    {
        return [
            'disabled_at' => 'datetime',
        ];
    }

    public function promoter()
    {
        return $this->belongsTo(AffiliatePromoter::class, 'promoter_id');
    }

    public function referrals()
    {
        return $this->hasMany(AffiliateReferral::class, 'code', 'code');
    }

    public function commissions()
    {
        return $this->hasManyThrough(
            AffiliateCommission::class,
            AffiliateReferral::class,
            'code',
            'referral_id',
            'code',
            'id',
        );
    }
}
