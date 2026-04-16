<?php

namespace App\Console\Commands;

use App\Jobs\GenerateClashProfileLink;
use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
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
    public function handle()
    {
        $payments = Payment::where('status', Payment::STATUS_CREATED)->get();

        /** @var Payment $payment */
        foreach ($payments as $payment) {
            if ($payment->gateway === Payment::GATEWAY_ALIPAY) {
                $this->processAlipay($payment);
            }

            if ($payment->gateway === Payment::GATEWAY_USDT) {
                $this->processUsdt($payment);
            }

            if ($payment->gateway === Payment::GATEWAY_STRIPE) {
                $this->processStripe($payment);
            }
        }
    }

    private function processAlipay(Payment $payment)
    {
        if ($payment->status !== Payment::STATUS_CREATED) {
            return false;
        }

        $result = Pay::alipay()->query([
            'out_trade_no' => $payment->remote_id,
        ]);

        if ($result->get('trade_status') === 'TRADE_SUCCESS') {
            DB::transaction(function () use ($payment, $result) {
                $payment = Payment::lockForUpdate()->find($payment->id);
                if ($payment->status !== Payment::STATUS_CREATED) {
                    return;
                }

                $payment->status = Payment::STATUS_PAID;
                $payload = $payment->payload;
                $payload[Payment::STATUS_PAID] = $result->toArray();
                $payment->payload = $payload;
                $payment->save();

                $payment->user->increment('balance', $payment->amount);

                $payment->user->balanceDetails()->create([
                    'amount' => $payment->amount,
                    'description' => 'Alipay payment',
                ]);

                GenerateClashProfileLink::dispatch();
            });

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

    private function processStripe(Payment $payment)
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
                DB::transaction(function () use ($payment, $session) {
                    $payment = Payment::lockForUpdate()->find($payment->id);
                    if ($payment->status !== Payment::STATUS_CREATED) {
                        return;
                    }

                    $payment->status = Payment::STATUS_PAID;
                    $payload = $payment->payload;
                    $payload[Payment::STATUS_PAID] = [
                        'session_id' => $session->id,
                        'payment_intent' => $session->payment_intent,
                        'payment_status' => $session->payment_status,
                    ];
                    $payment->payload = $payload;
                    $payment->save();

                    $payment->user->increment('balance', $payment->amount);

                    $payment->user->balanceDetails()->create([
                        'amount' => $payment->amount,
                        'description' => 'Stripe payment',
                    ]);

                    GenerateClashProfileLink::dispatch();
                });

                return true;
            }
        } catch (\Throwable $e) {
            logger()->error('Stripe session query failed: '.$e->getMessage());
        }

        return false;
    }
}
