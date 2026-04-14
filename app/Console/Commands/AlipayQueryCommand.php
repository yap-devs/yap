<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Yansongda\LaravelPay\Facades\Pay;

class AlipayQueryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:alipay-query-command
                            {--trade-no= : The out_trade_no to query}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Query a standalone Alipay payment order status.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $trade_no = $this->option('trade-no');

        if (! $trade_no) {
            $trade_no = $this->ask('Enter the trade number (out_trade_no)');
        }

        if (! $trade_no) {
            $this->error('Trade number is required.');

            return;
        }

        $this->info("Querying Alipay order: {$trade_no}");
        $this->newLine();

        try {
            $result = Pay::alipay()->query([
                'out_trade_no' => $trade_no,
            ]);
        } catch (\Throwable $e) {
            $this->error('Alipay API error: '.$e->getMessage());

            return;
        }

        $trade_status = $result->get('trade_status', 'N/A');
        $code = $result->get('code', 'N/A');
        $msg = $result->get('msg', 'N/A');

        $this->table(
            ['Field', 'Value'],
            [
                ['Code', $code],
                ['Message', $msg],
                ['Trade No', $result->get('out_trade_no', $trade_no)],
                ['Trade Status', $trade_status],
                ['Total Amount', $result->get('total_amount', 'N/A')],
                ['Buyer Logon ID', $result->get('buyer_logon_id', 'N/A')],
                ['Send Pay Date', $result->get('send_pay_date', 'N/A')],
            ]
        );

        // Status summary
        $this->newLine();
        match ($trade_status) {
            'TRADE_SUCCESS' => $this->info('Payment successful!'),
            'WAIT_BUYER_PAY' => $this->warn('Waiting for buyer to pay...'),
            'TRADE_CLOSED' => $this->error('Trade closed (timeout or refunded).'),
            'TRADE_FINISHED' => $this->info('Trade finished (cannot refund).'),
            default => $this->comment("Status: {$trade_status}"),
        };
    }
}
