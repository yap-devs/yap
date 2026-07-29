<?php

namespace App\Services\Affiliate;

use App\Models\AffiliatePromoter;
use App\Models\AffiliateReferralCode;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AffiliateReferralCodeService
{
    public function __construct(private readonly AffiliateLevelService $levelService) {}

    public function ensurePrimaryCode(AffiliatePromoter $promoter): AffiliateReferralCode
    {
        $referral_code = AffiliateReferralCode::query()
            ->where('code', $promoter->code)
            ->first();

        if ($referral_code) {
            throw_if(
                $referral_code->promoter_id !== $promoter->id || $referral_code->type !== AffiliateReferralCode::TYPE_SYSTEM,
                RuntimeException::class,
                'The promoter system code belongs to another referral code record.',
            );

            return $referral_code;
        }

        return AffiliateReferralCode::create([
            'promoter_id' => $promoter->id,
            'code' => $promoter->code,
            'type' => AffiliateReferralCode::TYPE_SYSTEM,
            'status' => AffiliateReferralCode::STATUS_ACTIVE,
        ]);
    }

    public function create(AffiliatePromoter $promoter, User $user, string $code): AffiliateReferralCode
    {
        try {
            return DB::transaction(function () use ($promoter, $user, $code): AffiliateReferralCode {
                /** @var AffiliatePromoter $promoter */
                $promoter = AffiliatePromoter::query()->lockForUpdate()->findOrFail($promoter->id);
                throw_if((int) $promoter->user_id !== (int) $user->id, ValidationException::withMessages([
                    'code' => __('messages.affiliate.code_validation.not_owned'),
                ]));
                $this->ensurePromoterIsActive($promoter);
                $quota = $this->quota($promoter, $user);

                if ($quota['active_count'] >= $quota['maximum']) {
                    throw ValidationException::withMessages([
                        'code' => __('messages.affiliate.code_validation.limit_reached'),
                    ]);
                }

                if ($quota['available_count'] < 1) {
                    throw ValidationException::withMessages([
                        'code' => __('messages.affiliate.code_validation.cooldown_active', [
                            'hours' => config('affiliate.referral_code_cooldown_hours'),
                        ]),
                    ]);
                }

                return AffiliateReferralCode::create([
                    'promoter_id' => $promoter->id,
                    'code' => Str::lower(trim($code)),
                    'type' => AffiliateReferralCode::TYPE_CUSTOM,
                    'status' => AffiliateReferralCode::STATUS_ACTIVE,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'code' => __('messages.affiliate.code_validation.taken'),
            ]);
        }
    }

    public function disable(AffiliatePromoter $promoter, AffiliateReferralCode $referral_code): void
    {
        DB::transaction(function () use ($promoter, $referral_code): void {
            /** @var AffiliatePromoter $promoter */
            $promoter = AffiliatePromoter::query()->lockForUpdate()->findOrFail($promoter->id);
            /** @var AffiliateReferralCode $referral_code */
            $referral_code = AffiliateReferralCode::query()->lockForUpdate()->findOrFail($referral_code->id);

            throw_if($referral_code->promoter_id !== $promoter->id, ValidationException::withMessages([
                'code' => __('messages.affiliate.code_validation.not_owned'),
            ]));

            throw_if($referral_code->type === AffiliateReferralCode::TYPE_SYSTEM, ValidationException::withMessages([
                'code' => __('messages.affiliate.code_validation.system_code'),
            ]));

            if ($referral_code->status === AffiliateReferralCode::STATUS_DISABLED) {
                return;
            }

            $referral_code->update([
                'status' => AffiliateReferralCode::STATUS_DISABLED,
                'disabled_at' => now(),
            ]);
        });
    }

    public function enable(AffiliatePromoter $promoter, User $user, AffiliateReferralCode $referral_code): void
    {
        DB::transaction(function () use ($promoter, $user, $referral_code): void {
            /** @var AffiliatePromoter $promoter */
            $promoter = AffiliatePromoter::query()->lockForUpdate()->findOrFail($promoter->id);
            /** @var AffiliateReferralCode $referral_code */
            $referral_code = AffiliateReferralCode::query()->lockForUpdate()->findOrFail($referral_code->id);

            throw_if($referral_code->promoter_id !== $promoter->id, ValidationException::withMessages([
                'code' => __('messages.affiliate.code_validation.not_owned'),
            ]));

            if ($referral_code->status === AffiliateReferralCode::STATUS_ACTIVE) {
                return;
            }

            $this->ensurePromoterIsActive($promoter);
            $quota = $this->quota($promoter, $user);
            if ($quota['active_count'] >= $quota['maximum']) {
                throw ValidationException::withMessages([
                    'code' => __('messages.affiliate.code_validation.limit_reached'),
                ]);
            }

            $referral_code->update([
                'status' => AffiliateReferralCode::STATUS_ACTIVE,
                'disabled_at' => null,
            ]);
        });
    }

    /**
     * @return array{maximum: int, active_count: int, cooling_count: int, available_count: int, creation_available_at: ?string}
     */
    public function quota(AffiliatePromoter $promoter, User $user): array
    {
        $maximum = max((int) $this->levelService->currentLevel($user)->maximum_referral_codes, 1);
        $active_count = $promoter->referralCodes()
            ->where('status', AffiliateReferralCode::STATUS_ACTIVE)
            ->count();
        $cooldown_threshold = now()->subHours((int) config('affiliate.referral_code_cooldown_hours'));
        $cooling_query = $promoter->referralCodes()
            ->where('status', AffiliateReferralCode::STATUS_DISABLED)
            ->where('disabled_at', '>', $cooldown_threshold);
        $cooling_count = (clone $cooling_query)->count();
        $oldest_cooling_at = (clone $cooling_query)->min('disabled_at');
        $available_count = max($maximum - $active_count - $cooling_count, 0);
        $is_waiting_for_cooldown = $active_count < $maximum
            && $available_count === 0
            && $cooling_count > 0;

        return [
            'maximum' => $maximum,
            'active_count' => $active_count,
            'cooling_count' => $cooling_count,
            'available_count' => $available_count,
            'creation_available_at' => $is_waiting_for_cooldown && $oldest_cooling_at
                ? Carbon::parse($oldest_cooling_at)->addHours((int) config('affiliate.referral_code_cooldown_hours'))->toDateTimeString()
                : null,
        ];
    }

    private function ensurePromoterIsActive(AffiliatePromoter $promoter): void
    {
        if ($promoter->status !== AffiliatePromoter::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'code' => __('messages.affiliate.code_validation.promoter_blocked'),
            ]);
        }
    }
}
