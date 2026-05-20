<?php

namespace App\Models;

use App\SerializeDate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AffiliateReferral extends Model
{
    use HasFactory, SerializeDate, SoftDeletes;

    const STATUS_REGISTERED = 'registered';

    const STATUS_QUALIFIED = 'qualified';

    const STATUS_EARNING = 'earning';

    const STATUS_EXPIRED = 'expired';

    const STATUS_REJECTED = 'rejected';

    const STATUS_BLOCKED = 'blocked';

    protected $fillable = [
        'promoter_id',
        'referrer_user_id',
        'referred_user_id',
        'code',
        'status',
        'landing_path',
        'source',
        'ip_hash',
        'user_agent_hash',
        'registered_at',
        'qualified_at',
        'first_qualified_payment_id',
        'first_qualified_payment_amount',
        'commission_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'qualified_at' => 'datetime',
            'first_qualified_payment_amount' => 'decimal:2',
            'commission_expires_at' => 'datetime',
        ];
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

    public function firstQualifiedPayment()
    {
        return $this->belongsTo(Payment::class, 'first_qualified_payment_id');
    }

    public function commissions()
    {
        return $this->hasMany(AffiliateCommission::class, 'referral_id');
    }
}
