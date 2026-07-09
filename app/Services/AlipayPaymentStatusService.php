<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\Cache;
use Yansongda\LaravelPay\Facades\Pay;

use function Illuminate\Support\defer;

readonly class AlipayPaymentStatusService
{
    private const CACHE_TTL_SECONDS = 5;

    public function __construct(private PaymentFulfillmentService $paymentFulfillmentService) {}

    public function snapshot(Payment $payment): array
    {
        $trade_status = $this->localTradeStatus($payment);
        if ($trade_status !== null || $payment->status !== Payment::STATUS_CREATED) {
            return $this->response($payment, $trade_status);
        }

        $cache_key = $this->cacheKey($payment);
        $cached_status = Cache::get($cache_key);
        if (is_array($cached_status)) {
            return $this->response($payment, $cached_status['trade_status'] ?? null);
        }

        if (Cache::add($cache_key, ['trade_status' => null], now()->addSeconds(self::CACHE_TTL_SECONDS))) {
            defer(fn () => $this->refresh($payment), 'alipay-refresh-payment-'.$payment->id)->always();
        }

        return $this->response($payment, null);
    }

    public function refresh(Payment $payment): ?array
    {
        if ($payment->status !== Payment::STATUS_CREATED || $payment->gateway !== Payment::GATEWAY_ALIPAY) {
            return null;
        }

        $result = Pay::alipay()->query([
            'out_trade_no' => $payment->remote_id,
        ]);

        $payload = $result->toArray();
        $trade_status = $payload['trade_status'] ?? null;

        Cache::put($this->cacheKey($payment), [
            'trade_status' => $trade_status,
            'payload' => $payload,
        ], now()->addSeconds(self::CACHE_TTL_SECONDS));

        if ($trade_status === 'TRADE_SUCCESS') {
            $this->paymentFulfillmentService->fulfill($payment, $payload);
        }

        return $payload;
    }

    public function processPending(Payment $payment): bool
    {
        if ($payment->status !== Payment::STATUS_CREATED || $payment->gateway !== Payment::GATEWAY_ALIPAY) {
            return false;
        }

        $payload = $this->refresh($payment);
        if (($payload['trade_status'] ?? null) === 'TRADE_SUCCESS') {
            return true;
        }

        if ($payment->created_at->diffInHours(now()) > 1) {
            $payment->status = Payment::STATUS_EXPIRED;
            $payment->save();

            return true;
        }

        return false;
    }

    private function response(Payment $payment, ?string $trade_status): array
    {
        return [
            'payment_status' => $payment->status,
            'trade_status' => $trade_status,
        ];
    }

    private function localTradeStatus(Payment $payment): ?string
    {
        return match ($payment->status) {
            Payment::STATUS_PAID => 'TRADE_SUCCESS',
            Payment::STATUS_CANCELLED, Payment::STATUS_EXPIRED, Payment::STATUS_REFUNDED => 'TRADE_CLOSED',
            default => null,
        };
    }

    private function cacheKey(Payment $payment): string
    {
        return 'alipay:trade-status:'.$payment->id;
    }
}
