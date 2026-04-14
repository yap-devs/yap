<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Yansongda\LaravelPay\Facades\Pay;

class AlipayOrderCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:alipay-order-command
                            {--amount= : Amount in USD}
                            {--rmb= : Amount in RMB (bypasses USD to RMB conversion)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a standalone Alipay payment order and display QR code URL.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $amount_usd = $this->option('amount');
        $amount_rmb = $this->option('rmb');

        if (! $amount_usd && ! $amount_rmb) {
            $amount_rmb = $this->ask('Enter amount in RMB');
        }

        if ($amount_rmb) {
            $total_amount = bcmul((string) $amount_rmb, '1', 2);
        } else {
            $rate = config('yap.payment.usd_rmb_rate');
            $total_amount = bcmul((string) $amount_usd, (string) $rate, 2);
            $this->info("USD {$amount_usd} x {$rate} = RMB {$total_amount}");
        }

        if (bccomp($total_amount, '0.01', 2) < 0) {
            $this->error('Amount must be at least 0.01 RMB.');

            return;
        }

        $out_trade_no = time().random_int(100000, 999999);
        $subject = config('yap.payment.alipay.subject');

        $this->info('Creating Alipay order...');
        $this->info("  Trade No:  {$out_trade_no}");
        $this->info("  Amount:    RMB {$total_amount}");
        $this->info("  Subject:   {$subject}");
        $this->newLine();

        try {
            $result = Pay::alipay()->scan([
                'out_trade_no' => $out_trade_no,
                'total_amount' => $total_amount,
                'subject' => $subject,
            ]);
        } catch (\Throwable $e) {
            $this->error('Alipay API error: '.$e->getMessage());

            return;
        }

        if (($result['code'] ?? null) !== '10000') {
            $this->error('Alipay scan failed:');
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return;
        }

        $qr_code = $result['qr_code'] ?? '';

        $this->info('Order created successfully!');
        $this->newLine();

        $this->table(
            ['Field', 'Value'],
            [
                ['Trade No', $out_trade_no],
                ['Amount (RMB)', $total_amount],
                ['QR Code URL', $qr_code],
            ]
        );

        $this->newLine();
        $this->warn('Scan the QR code URL with Alipay to pay.');
        $this->info('To query this order, run:');
        $this->line("  php artisan app:alipay-query-command --trade-no={$out_trade_no}");
    }
}
