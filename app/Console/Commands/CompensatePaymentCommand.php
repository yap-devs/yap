<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\PaymentFulfillmentService;
use Illuminate\Console\Command;

class CompensatePaymentCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:compensate
        {payment : Payment ID to compensate}
        {--reason=Manual payment compensation : Reason stored in the paid payload}
        {--yes : Skip the confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manually compensate an expired or cancelled payment order as paid';

    /**
     * Execute the console command.
     */
    public function handle(PaymentFulfillmentService $paymentFulfillmentService): int
    {
        $payment = Payment::query()->with('user')->find($this->argument('payment'));

        if (! $payment) {
            $this->error('Payment not found.');

            return self::FAILURE;
        }

        if (! in_array($payment->status, [Payment::STATUS_EXPIRED, Payment::STATUS_CANCELLED], true)) {
            $this->error("Only expired or cancelled payments can be compensated. Current status: {$payment->status}.");

            return self::FAILURE;
        }

        $this->table(['Field', 'Value'], [
            ['Payment ID', $payment->id],
            ['User ID', $payment->user_id],
            ['User Email', $payment->user?->email ?? '-'],
            ['Gateway', $payment->gateway],
            ['Status', $payment->status],
            ['Amount', $payment->amount],
            ['Remote ID', $payment->remote_id ?? '-'],
        ]);

        if (! $this->option('yes') && ! $this->confirm('Compensate this payment as paid and credit the user balance?')) {
            $this->warn('Compensation cancelled.');

            return self::FAILURE;
        }

        $previous_status = $payment->status;
        $fulfilled = $paymentFulfillmentService->fulfill($payment, [
            'source' => 'artisan payment:compensate',
            'reason' => $this->option('reason'),
            'previous_status' => $previous_status,
            'compensated_at' => now()->toDateTimeString(),
        ]);

        if (! $fulfilled) {
            $this->error('Payment was not compensated. It may have already been paid.');

            return self::FAILURE;
        }

        $this->info('Payment compensated successfully.');

        return self::SUCCESS;
    }
}
