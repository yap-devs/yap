<?php

namespace App\Services;

use App\Jobs\GenerateClashProfileLink;
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

        DB::transaction(function () use ($payment, $paid_payload, &$fulfilled): void {
            /** @var Payment|null $payment */
            $payment = Payment::query()->lockForUpdate()->find($payment->id);
            if (! $payment || $payment->status === Payment::STATUS_PAID) {
                return;
            }

            $payment->status = Payment::STATUS_PAID;

            if ($paid_payload !== null) {
                $payload = $payment->payload ?? [];
                $payload[Payment::STATUS_PAID] = $paid_payload;
                $payment->payload = $payload;
            }

            $payment->save();

            $payment->user->increment('balance', $payment->amount);

            $payment->user->balanceDetails()->create([
                'amount' => $payment->amount,
                'description' => $this->balanceDescription($payment),
            ]);

            app(AffiliateService::class)->handlePaymentPaid($payment);

            GenerateClashProfileLink::dispatch();

            $fulfilled = true;
        });

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
}
