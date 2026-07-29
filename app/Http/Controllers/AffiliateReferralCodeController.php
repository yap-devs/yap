<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAffiliateReferralCodeRequest;
use App\Models\AffiliateReferralCode;
use App\Models\User;
use App\Services\Affiliate\AffiliateReferralCodeService;
use App\Services\Affiliate\AffiliateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AffiliateReferralCodeController extends Controller
{
    public function store(
        StoreAffiliateReferralCodeRequest $request,
        AffiliateService $affiliateService,
        AffiliateReferralCodeService $referralCodeService,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $promoter = $affiliateService->ensurePromoter($user);
        $validated = $request->validated();

        $referralCodeService->create($promoter, $user, $validated['code']);

        return back();
    }

    public function disable(
        Request $request,
        AffiliateReferralCode $affiliate_referral_code,
        AffiliateService $affiliateService,
        AffiliateReferralCodeService $referralCodeService,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $promoter = $affiliateService->ensurePromoter($user);
        abort_if($affiliate_referral_code->promoter_id !== $promoter->id, 404);

        $referralCodeService->disable($promoter, $affiliate_referral_code);

        return back();
    }

    public function enable(
        Request $request,
        AffiliateReferralCode $affiliate_referral_code,
        AffiliateService $affiliateService,
        AffiliateReferralCodeService $referralCodeService,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $promoter = $affiliateService->ensurePromoter($user);
        abort_if($affiliate_referral_code->promoter_id !== $promoter->id, 404);

        $referralCodeService->enable($promoter, $user, $affiliate_referral_code);

        return back();
    }
}
