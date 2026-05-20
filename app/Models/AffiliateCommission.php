<?php

namespace App\Models;

use App\SerializeDate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AffiliateCommission extends Model
{
    use HasFactory, SerializeDate, SoftDeletes;

    const SOURCE_PACKAGE_PURCHASE = 'package_purchase';

    const STATUS_PENDING = 'pending';

    const STATUS_CREDITED = 'credited';

    const STATUS_REJECTED = 'rejected';

    const STATUS_REVERSED = 'reversed';

    protected $fillable = [
        'referral_id',
        'promoter_id',
        'referrer_user_id',
        'referred_user_id',
        'source_type',
        'source_id',
        'affiliate_level',
        'base_amount',
        'commission_rate',
        'amount',
        'status',
        'hold_until',
        'credited_at',
        'credited_balance_detail_id',
        'reversed_at',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'base_amount' => 'decimal:2',
            'commission_rate' => 'decimal:4',
            'amount' => 'decimal:2',
            'hold_until' => 'datetime',
            'credited_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function referral()
    {
        return $this->belongsTo(AffiliateReferral::class, 'referral_id');
    }

    public function promoter()
    {
        return $this->belongsTo(AffiliatePromoter::class, 'promoter_id');
    }

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referrer_user_id');
    }

    public function referred()
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }

    public function creditedBalanceDetail()
    {
        return $this->belongsTo(BalanceDetail::class, 'credited_balance_detail_id');
    }
}
