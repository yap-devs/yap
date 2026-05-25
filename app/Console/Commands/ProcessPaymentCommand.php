<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\PaymentFulfillmentService;
use Illuminate\Console\Command;
use Stripe\Checkout\Session;
use Stripe\Stripe;
use Yansongda\LaravelPay\Facades\Pay;

class ProcessPaymentCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:process-payment-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process payments';

    /**
     * Execute the console command.
     */
    public function handle(PaymentFulfillmentService $paymentFulfillmentService)
    {
        $payments = Payment::where('status', Payment::STATUS_CREATED)->get();

        /** @var Payment $payment */
        foreach ($payments as $payment) {
            if ($payment->gateway === Payment::GATEWAY_ALIPAY) {
                $this->processAlipay($payment, $paymentFulfillmentService);
            }

            if ($payment->gateway === Payment::GATEWAY_USDT) {
                $this->processUsdt($payment);
            }

            if ($payment->gateway === Payment::GATEWAY_STRIPE) {
                $this->processStripe($payment, $paymentFulfillmentService);
            }
        }
    }

    private function processAlipay(Payment $payment, PaymentFulfillmentService $paymentFulfillmentService)
    {
        if ($payment->status !== Payment::STATUS_CREATED) {
            return false;
        }

        $result = Pay::alipay()->query([
            'out_trade_no' => $payment->remote_id,
        ]);

        if ($result->get('trade_status') === 'TRADE_SUCCESS') {
            $paymentFulfillmentService->fulfill($payment, $result->toArray());

            return true;
        }

        if ($payment->created_at->diffInHours(now()) > 1) {
            $payment->status = Payment::STATUS_EXPIRED;
            $payment->save();

            return true;
        }

        return false;
    }

    private function processUsdt(Payment $payment)
    {
        if ($payment->status !== Payment::STATUS_CREATED) {
            return false;
        }

        if ($payment->created_at->diffInSeconds(now()) > 1200) {
            $payment->status = Payment::STATUS_EXPIRED;
            $payment->save();

            return true;
        }

        return false;
    }

    private function processStripe(Payment $payment, PaymentFulfillmentService $paymentFulfillmentService)
    {
        if ($payment->status !== Payment::STATUS_CREATED) {
            return false;
        }

        // Stripe Checkout sessions expire after 24 hours by default
        if ($payment->created_at->diffInHours(now()) > 24) {
            $payment->status = Payment::STATUS_EXPIRED;
            $payment->save();

            return true;
        }

        // Check session status as a safety net for missed webhooks
        $session_id = $payment->payload[Payment::STATUS_CREATED]['session_id'] ?? null;
        if (! $session_id) {
            return false;
        }

        try {
            Stripe::setApiKey(config('yap.payment.stripe.secret'));
            $session = Session::retrieve($session_id);

            if ($session->payment_status === 'paid') {
                $paymentFulfillmentService->fulfill($payment, [
                    'session_id' => $session->id,
                    'payment_intent' => $session->payment_intent,
                    'payment_status' => $session->payment_status,
                ]);

                return true;
            }
        } catch (\Throwable $e) {
            logger()->error('Stripe session query failed: '.$e->getMessage());
        }

        return false;
    }
}
