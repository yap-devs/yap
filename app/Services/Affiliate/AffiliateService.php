<?php

namespace App\Services\Affiliate;

use App\Jobs\GenerateClashProfileLink;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePromoter;
use App\Models\AffiliateReferral;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserPackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AffiliateService
{
    public const COOKIE_NAME = 'affiliate_ref';

    public function __construct(private readonly AffiliateLevelService $levelService) {}

    public function ensurePromoter(User $user): AffiliatePromoter
    {
        return DB::transaction(function () use ($user): AffiliatePromoter {
            User::query()->where('id', $user->id)->lockForUpdate()->first();

            $promoter = AffiliatePromoter::query()
                ->where('user_id', $user->id)
                ->first();

            if ($promoter) {
                return $promoter;
            }

            return AffiliatePromoter::create([
                'user_id' => $user->id,
                'code' => $this->generateCode($user),
                'status' => AffiliatePromoter::STATUS_ACTIVE,
            ]);
        });
    }

    public function captureReferral(Request $request): void
    {
        if (! config('affiliate.enabled')) {
            return;
        }

        $code = $request->query('ref');
        if (! is_string($code) || $code === '') {
            return;
        }

        $promoter = AffiliatePromoter::query()
            ->where('code', $code)
            ->where('status', AffiliatePromoter::STATUS_ACTIVE)
            ->first();

        if (! $promoter) {
            return;
        }

        if (config('affiliate.attribution_type') === 'first_click' && $request->cookie(self::COOKIE_NAME)) {
            return;
        }

        Cookie::queue(cookie(
            self::COOKIE_NAME,
            $code,
            (int) config('affiliate.cookie_days') * 24 * 60,
            null,
            null,
            $request->isSecure(),
            true,
            false,
            'lax',
        ));
    }

    public function createReferralFromCookie(Request $request, User $user): void
    {
        if (! config('affiliate.enabled')) {
            return;
        }

        $code = $request->cookie(self::COOKIE_NAME);
        if (! is_string($code) || $code === '') {
            return;
        }

        DB::transaction(function () use ($request, $user, $code): void {
            if (AffiliateReferral::query()->where('referred_user_id', $user->id)->exists()) {
                return;
            }

            $promoter = AffiliatePromoter::query()
                ->where('code', $code)
                ->where('status', AffiliatePromoter::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();

            if (! $promoter || (int) $promoter->user_id === (int) $user->id) {
                return;
            }

            AffiliateReferral::create([
                'promoter_id' => $promoter->id,
                'referrer_user_id' => $promoter->user_id,
                'referred_user_id' => $user->id,
                'code' => $code,
                'status' => AffiliateReferral::STATUS_REGISTERED,
                'landing_path' => $this->limitString($request->headers->get('referer')),
                'source' => $this->limitString($request->query('utm_source')),
                'ip_hash' => $this->hashNullable($request->ip()),
                'user_agent_hash' => $this->hashNullable($request->userAgent()),
                'registered_at' => now(),
            ]);
        });
    }

    public function handlePaymentPaid(Payment $payment): void
    {
        if (! $this->isEligiblePayment($payment)) {
            return;
        }

        DB::transaction(function () use ($payment): void {
            $payment = Payment::query()->lockForUpdate()->find($payment->id);
            if (! $payment || ! $this->isEligiblePayment($payment)) {
                return;
            }

            $referral = AffiliateReferral::query()
                ->where('referred_user_id', $payment->user_id)
                ->where('status', AffiliateReferral::STATUS_REGISTERED)
                ->lockForUpdate()
                ->first();

            if (! $referral) {
                return;
            }

            $referral->update([
                'status' => AffiliateReferral::STATUS_QUALIFIED,
                'qualified_at' => now(),
                'first_qualified_payment_id' => $payment->id,
                'first_qualified_payment_amount' => $payment->amount,
                'commission_expires_at' => now()->addDays((int) config('affiliate.commission_expires_days')),
            ]);

            AffiliatePromoter::query()
                ->where('id', $referral->promoter_id)
                ->increment('total_valid_referrals');
        });
    }

    public function handlePackagePurchased(UserPackage $userPackage): void
    {
        if (! config('affiliate.enabled')) {
            return;
        }

        DB::transaction(function () use ($userPackage): void {
            $userPackage = UserPackage::query()->with('package')->lockForUpdate()->find($userPackage->id);
            if (! $userPackage || ! $userPackage->package) {
                return;
            }

            $referral = AffiliateReferral::query()
                ->where('referred_user_id', $userPackage->user_id)
                ->whereIn('status', [AffiliateReferral::STATUS_QUALIFIED, AffiliateReferral::STATUS_EARNING])
                ->where('commission_expires_at', '>=', now())
                ->lockForUpdate()
                ->first();

            if (! $referral || $this->hasCommissionForPackage($referral, $userPackage)) {
                return;
            }

            $promoter = AffiliatePromoter::query()
                ->where('id', $referral->promoter_id)
                ->where('status', AffiliatePromoter::STATUS_ACTIVE)
                ->lockForUpdate()
                ->first();

            if (! $promoter) {
                return;
            }

            /** @var User|null $referrer */
            $referrer = User::query()->find($referral->referrer_user_id);
            if (! $referrer || $this->levelService->selfPaidTotal($referrer) < (float) config('affiliate.minimum_referrer_paid_amount')) {
                return;
            }

            $level = $this->levelService->currentLevel($referrer);
            $rate = $promoter->custom_commission_rate ?? $level->commission_rate;
            $base_amount = (string) $userPackage->package->price;
            $amount = bcmul($base_amount, (string) $rate, 2);

            if ((float) $amount < (float) config('affiliate.minimum_commission_amount')) {
                return;
            }

            AffiliateCommission::create([
                'referral_id' => $referral->id,
                'promoter_id' => $promoter->id,
                'referrer_user_id' => $referral->referrer_user_id,
                'referred_user_id' => $referral->referred_user_id,
                'source_type' => AffiliateCommission::SOURCE_PACKAGE_PURCHASE,
                'source_id' => $userPackage->id,
                'affiliate_level' => $level->level,
                'base_amount' => $base_amount,
                'commission_rate' => $rate,
                'amount' => $amount,
                'status' => AffiliateCommission::STATUS_PENDING,
                'hold_until' => now()->addDays((int) config('affiliate.pending_days')),
            ]);

            if ($referral->status !== AffiliateReferral::STATUS_EARNING) {
                $referral->update(['status' => AffiliateReferral::STATUS_EARNING]);
            }
        });
    }

    public function creditPendingCommissions(): int
    {
        $credited = 0;

        AffiliateCommission::query()
            ->where('status', AffiliateCommission::STATUS_PENDING)
            ->where('hold_until', '<=', now())
            ->orderBy('id')
            ->chunkById(100, function ($commissions) use (&$credited): void {
                foreach ($commissions as $commission) {
                    DB::transaction(function () use ($commission, &$credited): void {
                        $commission = AffiliateCommission::query()->lockForUpdate()->find($commission->id);
                        if (! $commission || $commission->status !== AffiliateCommission::STATUS_PENDING) {
                            return;
                        }

                        $referral = AffiliateReferral::query()->find($commission->referral_id);
                        $promoter = AffiliatePromoter::query()->find($commission->promoter_id);
                        if (! $referral || ! $promoter || $promoter->status !== AffiliatePromoter::STATUS_ACTIVE) {
                            $commission->update([
                                'status' => AffiliateCommission::STATUS_REJECTED,
                                'reason' => 'Promoter or referral is not active.',
                            ]);

                            return;
                        }

                        if (in_array($referral->status, [AffiliateReferral::STATUS_REJECTED, AffiliateReferral::STATUS_BLOCKED], true)) {
                            $commission->update([
                                'status' => AffiliateCommission::STATUS_REJECTED,
                                'reason' => 'Referral is not eligible.',
                            ]);

                            return;
                        }

                        /** @var User|null $referrer */
                        $referrer = User::query()->lockForUpdate()->find($commission->referrer_user_id);
                        if (! $referrer) {
                            return;
                        }

                        $referrer->increment('balance', $commission->amount);
                        $balance_detail = $referrer->balanceDetails()->create([
                            'amount' => $commission->amount,
                            'description' => __('messages.balance_descriptions.affiliate_commission', [], 'en'),
                        ]);

                        $commission->update([
                            'status' => AffiliateCommission::STATUS_CREDITED,
                            'credited_at' => now(),
                            'credited_balance_detail_id' => $balance_detail->id,
                        ]);

                        $promoter->increment('total_commission_amount', $commission->amount);
                        GenerateClashProfileLink::dispatch();
                        $credited++;
                    });
                }
            });

        return $credited;
    }

    public function expireReferrals(): int
    {
        return AffiliateReferral::query()
            ->whereIn('status', [AffiliateReferral::STATUS_QUALIFIED, AffiliateReferral::STATUS_EARNING])
            ->where('commission_expires_at', '<=', now())
            ->update(['status' => AffiliateReferral::STATUS_EXPIRED]);
    }

    private function isEligiblePayment(Payment $payment): bool
    {
        return config('affiliate.enabled')
            && $payment->status === Payment::STATUS_PAID
            && in_array($payment->gateway, config('affiliate.allowed_gateways'), true)
            && (float) $payment->amount >= (float) config('affiliate.minimum_referred_first_payment_amount');
    }

    private function generateCode(User $user): string
    {
        do {
            $code = Str::lower(Str::random(8));
        } while (AffiliatePromoter::query()->where('code', $code)->exists());

        return $code;
    }

    private function hashNullable(?string $value): ?string
    {
        return $value ? hash('sha256', $value) : null;
    }

    private function limitString(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return Str::substr($value, 0, 255);
    }

    private function hasCommissionForPackage(AffiliateReferral $referral, UserPackage $userPackage): bool
    {
        return AffiliateCommission::query()
            ->where('referral_id', $referral->id)
            ->where('source_type', AffiliateCommission::SOURCE_PACKAGE_PURCHASE)
            ->where('source_id', $userPackage->id)
            ->exists();
    }
}
