<?php

namespace App\Services;

use App\Jobs\GenerateClashProfileLink;
use App\Jobs\SyncSub2apiUser;
use App\Models\Payment;
use App\Services\Affiliate\AffiliateService;
use Illuminate\Support\Facades\DB;

class PaymentFulfillmentService
{
    /**
     * Mark a payment as paid and apply all side effects for a successful recharge.
     */
    public function fulfill(Payment $payment, ?array $paid_payload = null): bool
    {
        $fulfilled = false;
        $fulfilled_user_id = null;
        $should_sync_clash_profile = false;

        DB::transaction(function () use ($payment, $paid_payload, &$fulfilled, &$fulfilled_user_id, &$should_sync_clash_profile): void {
            /** @var Payment|null $payment */
            $payment = Payment::query()->lockForUpdate()->find($payment->id);
            if (! $payment || $payment->status === Payment::STATUS_PAID) {
                return;
            }

            $user = $payment->user()
                ->with(['packages' => function ($query) {
                    $query->available();
                }])
                ->lockForUpdate()
                ->firstOrFail();
            $is_valid_initial = $user->is_valid;
            $is_low_priority_initial = $user->is_low_priority;

            $payment->status = Payment::STATUS_PAID;

            if ($paid_payload !== null) {
                $payload = $payment->payload ?? [];
                $payload[Payment::STATUS_PAID] = $paid_payload;
                $payment->payload = $payload;
            }

            $payment->save();

            $user->increment('balance', $payment->amount);

            $user->balanceDetails()->create([
                'amount' => $payment->amount,
                'description' => $this->balanceDescription($payment),
            ]);

            app(AffiliateService::class)->handlePaymentPaid($payment);

            $user->refresh();
            $user->load(['packages' => function ($query) {
                $query->available();
            }]);
            $should_sync_clash_profile = $user->is_valid !== $is_valid_initial
                || $user->is_low_priority !== $is_low_priority_initial;

            $fulfilled = true;
            $fulfilled_user_id = $payment->user_id;
        });

        if ($fulfilled && $should_sync_clash_profile) {
            GenerateClashProfileLink::dispatch();
        }

        if ($fulfilled && $fulfilled_user_id !== null) {
            $this->dispatchSub2apiSyncForUser($fulfilled_user_id);
        }

        return $fulfilled;
    }

    private function balanceDescription(Payment $payment): string
    {
        return match ($payment->gateway) {
            Payment::GATEWAY_ALIPAY => __('messages.balance_descriptions.alipay_payment', [], 'en'),
            Payment::GATEWAY_USDT => __('messages.balance_descriptions.usdt_payment', [], 'en'),
            Payment::GATEWAY_STRIPE => __('messages.balance_descriptions.stripe_payment', [], 'en'),
            Payment::GATEWAY_GITHUB => __('messages.balance_descriptions.github_sponsor', [], 'en'),
            default => 'Payment recharge',
        };
    }

    public function dispatchSub2apiSyncForUser(int $user_id): void
    {
        SyncSub2apiUser::dispatch($user_id);
    }
}
