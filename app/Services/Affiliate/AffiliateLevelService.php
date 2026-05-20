<?php

namespace App\Services\Affiliate;

use App\Models\AffiliateLevel;
use App\Models\AffiliateReferral;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Collection;

class AffiliateLevelService
{
    public function selfPaidTotal(User $user): float
    {
        return (float) $user->payments()
            ->where('status', Payment::STATUS_PAID)
            ->whereIn('gateway', config('affiliate.allowed_gateways'))
            ->sum('amount');
    }

    public function validReferralCount(User $user): int
    {
        return $user->affiliateReferrals()
            ->whereIn('status', [
                AffiliateReferral::STATUS_QUALIFIED,
                AffiliateReferral::STATUS_EARNING,
                AffiliateReferral::STATUS_EXPIRED,
            ])
            ->whereNotNull('qualified_at')
            ->count();
    }

    public function currentLevel(User $user): AffiliateLevel
    {
        $self_paid_total = $this->selfPaidTotal($user);
        $valid_referral_count = $this->validReferralCount($user);

        $level = AffiliateLevel::query()
            ->where('status', AffiliateLevel::STATUS_ACTIVE)
            ->where('minimum_self_paid_amount', '<=', $self_paid_total)
            ->where('minimum_valid_referrals', '<=', $valid_referral_count)
            ->orderByDesc('level')
            ->first();

        if ($level) {
            return $level;
        }

        return new AffiliateLevel([
            'level' => 0,
            'name' => 'Visitor',
            'minimum_self_paid_amount' => 0,
            'minimum_valid_referrals' => 0,
            'commission_rate' => 0,
            'status' => AffiliateLevel::STATUS_ACTIVE,
        ]);
    }

    public function nextLevel(User $user): ?AffiliateLevel
    {
        return AffiliateLevel::query()
            ->where('status', AffiliateLevel::STATUS_ACTIVE)
            ->where('level', '>', $this->currentLevel($user)->level)
            ->orderBy('level')
            ->first();
    }

    public function levels(): Collection
    {
        return AffiliateLevel::query()
            ->where('status', AffiliateLevel::STATUS_ACTIVE)
            ->orderBy('level')
            ->get();
    }
}
