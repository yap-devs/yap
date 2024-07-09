<?php

namespace App\Console\Commands;

use App\Models\Payment;
use Illuminate\Console\Command;

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

        if ($payment->created_at->diffInHours(now()) > 1) {
            $payment->status = Payment::STATUS_EXPIRED;
            $payment->save();
            return true;
        }

        return false;
    }
}
