<?php

namespace App\Services\Affiliate;

use App\Models\AffiliateCommission;
use App\Models\AffiliateReferral;
use App\Models\User;
use Illuminate\Support\Facades\URL;

class AffiliateDashboardService
{
    public function __construct(
        private readonly AffiliateLevelService $levelService,
        private readonly AffiliateService $affiliateService,
    ) {}

    public function dashboard(User $user): array
    {
        $promoter = $this->affiliateService->ensurePromoter($user);
        $current_level = $this->levelService->currentLevel($user);
        $next_level = $this->levelService->nextLevel($user);
        $self_paid_total = $this->levelService->selfPaidTotal($user);
        $valid_referral_count = $this->levelService->validReferralCount($user);

        $referrals = $promoter->referrals()
            ->with(['referred:id,name,email,github_nickname', 'commissions'])
            ->latest()
            ->get()
            ->map(fn (AffiliateReferral $referral): array => $this->formatReferral($referral, $self_paid_total));

        $commissions = $promoter->commissions()
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (AffiliateCommission $commission): array => [
                'id' => $commission->id,
                'status' => $commission->status,
                'base_amount' => (string) $commission->base_amount,
                'commission_rate' => (float) $commission->commission_rate,
                'amount' => (string) $commission->amount,
                'hold_until' => $commission->hold_until?->toDateTimeString(),
                'credited_at' => $commission->credited_at?->toDateTimeString(),
                'created_at' => $commission->created_at?->toDateTimeString(),
            ]);

        return [
            'promoter' => [
                'code' => $promoter->code,
                'status' => $promoter->status,
                'url' => URL::to('/?ref='.$promoter->code),
            ],
            'stats' => [
                'self_paid_total' => number_format($self_paid_total, 2, '.', ''),
                'valid_referral_count' => $valid_referral_count,
                'pending_commission' => number_format((float) $promoter->commissions()->where('status', AffiliateCommission::STATUS_PENDING)->sum('amount'), 2, '.', ''),
                'credited_commission' => number_format((float) $promoter->commissions()->where('status', AffiliateCommission::STATUS_CREDITED)->sum('amount'), 2, '.', ''),
            ],
            'current_level' => [
                'level' => $current_level->level,
                'name' => $current_level->name,
                'commission_rate' => (float) ($promoter->custom_commission_rate ?? $current_level->commission_rate),
            ],
            'next_level' => $next_level ? [
                'level' => $next_level->level,
                'name' => $next_level->name,
                'minimum_self_paid_amount' => (string) $next_level->minimum_self_paid_amount,
                'minimum_valid_referrals' => $next_level->minimum_valid_referrals,
                'remaining_self_paid_amount' => number_format(max((float) $next_level->minimum_self_paid_amount - $self_paid_total, 0), 2, '.', ''),
                'remaining_valid_referrals' => max($next_level->minimum_valid_referrals - $valid_referral_count, 0),
                'commission_rate' => (float) $next_level->commission_rate,
            ] : null,
            'levels' => $this->levelService->levels()->map(fn ($level): array => [
                'level' => $level->level,
                'name' => $level->name,
                'minimum_self_paid_amount' => (string) $level->minimum_self_paid_amount,
                'minimum_valid_referrals' => $level->minimum_valid_referrals,
                'commission_rate' => (float) $level->commission_rate,
            ]),
            'referrals' => $referrals,
            'commissions' => $commissions,
            'rules' => [
                'minimum_referrer_paid_amount' => number_format((float) config('affiliate.minimum_referrer_paid_amount'), 2, '.', ''),
                'minimum_referred_first_payment_amount' => number_format((float) config('affiliate.minimum_referred_first_payment_amount'), 2, '.', ''),
                'pending_days' => (int) config('affiliate.pending_days'),
                'commission_expires_days' => (int) config('affiliate.commission_expires_days'),
            ],
        ];
    }

    private function formatReferral(AffiliateReferral $referral, float $self_paid_total): array
    {
        $pending = (float) $referral->commissions->where('status', AffiliateCommission::STATUS_PENDING)->sum('amount');
        $credited = (float) $referral->commissions->where('status', AffiliateCommission::STATUS_CREDITED)->sum('amount');
        $prompt_key = $referral->status;

        if ($referral->status === AffiliateReferral::STATUS_QUALIFIED && $self_paid_total < (float) config('affiliate.minimum_referrer_paid_amount')) {
            $prompt_key = 'qualified_ineligible';
        }

        return [
            'id' => $referral->id,
            'user_label' => $this->userLabel($referral->referred),
            'status' => $referral->status,
            'prompt_key' => $prompt_key,
            'registered_at' => $referral->registered_at?->toDateTimeString(),
            'qualified_at' => $referral->qualified_at?->toDateTimeString(),
            'commission_expires_at' => $referral->commission_expires_at?->toDateTimeString(),
            'first_qualified_payment_amount' => (string) $referral->first_qualified_payment_amount,
            'pending_commission' => number_format($pending, 2, '.', ''),
            'credited_commission' => number_format($credited, 2, '.', ''),
        ];
    }

    private function userLabel(?User $user): string
    {
        if (! $user) {
            return 'Unknown user';
        }

        if ($user->github_nickname) {
            return $user->github_nickname;
        }

        [$name, $domain] = explode('@', $user->email, 2) + ['', ''];
        $masked_name = substr($name, 0, 2).str_repeat('*', max(strlen($name) - 2, 1));

        return $masked_name.'@'.$domain;
    }
}
