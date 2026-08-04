<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateClashProfileLink;
use App\Models\Payment;
use App\Models\User;
use App\Services\Affiliate\AffiliateService;
use App\Services\PaymentFulfillmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class GithubController extends Controller
{
    private const GITHUB_LINK_ACTION_BIND = 'bind';

    private const GITHUB_LINK_ACTION_UNLINK = 'unlink';

    private const GITHUB_LINK_ATTEMPTS_PER_DAY = 2;

    public function redirect()
    {
        return Socialite::driver('github')->redirect();
    }

    public function callback(Request $request)
    {
        try {
            $user = Socialite::driver('github')->user();
        } catch (InvalidStateException) {
            return redirect()->route('profile.edit');
        }

        /** @var User $authenticated_user */
        $authenticated_user = $request->user();
        $linked_user = User::withTrashed()->where('github_id', $user->id)->first();

        abort_if($linked_user !== null && ! $linked_user->is($authenticated_user), 403, 'This GitHub account has been linked to another user.');

        if ((string) $authenticated_user->github_id === (string) $user->id) {
            return redirect()->route('profile.edit');
        }

        $this->hitGithubLinkRateLimit($request, $user->id, self::GITHUB_LINK_ACTION_BIND);

        $authenticated_user->update([
            'github_id' => $user->id,
            'github_nickname' => $user->nickname,
            'github_token' => $user->token,
            'github_created_at' => $user->user['created_at'],
        ]);

        GenerateClashProfileLink::dispatch();

        return redirect()->route('profile.edit');
    }

    public function destroy(Request $request)
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->github_id === null) {
            return redirect()->route('profile.edit');
        }

        $github_id = $user->github_id;
        $this->hitGithubLinkRateLimit($request, $github_id, self::GITHUB_LINK_ACTION_UNLINK);

        $updated = User::query()
            ->whereKey($user->getKey())
            ->where('github_id', $github_id)
            ->update([
                'github_id' => null,
                'github_nickname' => '',
                'github_token' => '',
                'github_created_at' => null,
            ]);

        if ($updated === 1) {
            GenerateClashProfileLink::dispatch();
        }

        return redirect()->route('profile.edit');
    }

    public function sponsorWebhook(Request $request, PaymentFulfillmentService $paymentFulfillmentService)
    {
        logger('GitHub sponsor webhook', $request->all());

        $user = User::where('github_id', $request->input('sponsorship.sponsor.id'))->first();

        if (! $user) {
            return response()->json(['message' => __('messages.errors.user_not_found')]);
        }

        if ($request->input('action') !== 'created') {
            return response()->json(['message' => __('messages.errors.ignoring_action')]);
        }

        $amount = $request->input('sponsorship.tier.monthly_price_in_dollars');

        if (! is_numeric($amount) || $amount <= 0) {
            logger()->warning('GitHub sponsor webhook: invalid amount', ['amount' => $amount]);

            return response()->json(['message' => __('messages.errors.invalid_amount')]);
        }

        $remote_id = $request->input('sponsorship.tier.node_id').'|'.$request->input('sponsorship.tier.created_at');

        $fulfilled_user_id = null;

        DB::transaction(function () use ($user, $amount, $remote_id, $request, &$fulfilled_user_id): void {
            // Lock matching rows (if any) to prevent duplicate processing.
            // Without lockForUpdate, two concurrent webhook deliveries could both
            // see exists()=false under REPEATABLE READ and double-credit the user.
            if (Payment::lockForUpdate()->where('remote_id', $remote_id)->exists()) {
                return;
            }

            /** @var Payment $payment */
            $payment = $user->payments()->create([
                'gateway' => Payment::GATEWAY_GITHUB,
                'status' => Payment::STATUS_PAID,
                'amount' => $amount,
                'remote_id' => $remote_id,
                'payload' => $request->all(),
            ]);

            $user->increment('balance', $amount);

            $user->balanceDetails()->create([
                'amount' => $amount,
                'description' => __('messages.balance_descriptions.github_sponsor', [], 'en'),
            ]);

            app(AffiliateService::class)->handlePaymentPaid($payment);

            GenerateClashProfileLink::dispatch();

            $fulfilled_user_id = $user->id;
        });

        if ($fulfilled_user_id !== null) {
            $paymentFulfillmentService->dispatchSub2apiSyncForUser($fulfilled_user_id);
        }

        return response()->json(['message' => __('messages.errors.ok')]);
    }

    private function hitGithubLinkRateLimit(Request $request, int|string $github_id, string $action): void
    {
        $user_id = $request->user()->getAuthIdentifier();
        $limits = [
            ['key' => 'github-link:day:'.$action.':user:'.$user_id, 'attempts' => self::GITHUB_LINK_ATTEMPTS_PER_DAY, 'decay' => 86400],
            ['key' => 'github-link:day:'.$action.':github:'.$github_id, 'attempts' => self::GITHUB_LINK_ATTEMPTS_PER_DAY, 'decay' => 86400],
        ];

        foreach ($limits as $limit) {
            $attempts = RateLimiter::hit($limit['key'], $limit['decay']);

            if ($attempts <= $limit['attempts']) {
                continue;
            }

            logger()->driver('throttle')->warning('RateLimiter [github-link]: '.$request->path(), [
                'user_id' => $user_id,
                'ip' => $request->ip(),
            ]);

            abort(429, 'Too many GitHub account changes.');
        }
    }
}
