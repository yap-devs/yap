<?php

namespace App\Console\Commands;

use App\Jobs\GenerateClashProfileLink;
use App\Models\Payment;
use Illuminate\Console\Command;
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
        $payments = Payment::all();

        /** @var Payment $payment */
        foreach ($payments as $payment) {
            if ($payment->gateway === Payment::GATEWAY_ALIPAY) {
                $this->processAlipay($payment);
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
            logger()->info('Alipay payment success by query: ' . $payment->remote_id);
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

            return true;
        }

        if ($payment->created_at->diffInHours(now()) > 1) {
            $payment->status = Payment::STATUS_EXPIRED;
            $payment->save();
            return true;
        }

        return false;
    }
}
