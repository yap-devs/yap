<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class BepusdtService
{
    const STATUS_WAIT = 1;
    const STATUS_SUCCESS = 2;
    const STATUS_EXPIRED = 3;

    protected $config;

    public function __construct()
    {
        $this->config = config('bepusdt');
    }

    /**
     * Create a transaction with Bepusdt.
     *
     * @param float|int $amount The amount to be processed in the payment.
     * @param int|string $order_id The unique identifier for the order.
     *
     * @return array The API response containing the transaction details, including the payment URL.
     *
     * @throws \Exception If the API response indicates an error.
     * @throws \Exception If the payment URL is not found in the API response.
     * @throws \Throwable
     */
    public function createTransaction($amount, $order_id)
    {
        $data = [
            'trade_type' => 'usdt.polygon',
            'order_id' => $order_id,
            'amount' => $amount,
            'notify_url' => route('bepusdt.notify'),
            'redirect_url' => route('profile.edit'),
        ];
        $signature = $this->epusdtSign($data, $this->config['auth_token']);
        $data['signature'] = $signature;

        $res = Http::post($this->config['app_uri'] . '/api/v1/order/create-transaction', $data);

        throw_if($res->json('status_code') !== 200, new \Exception('Bepusdt API error: ' . $res->json('message')));

        return $res->json();
    }

    /**
     * Check the callback from Bepusdt.
     *
     * @throws \Throwable
     */
    public function callback()
    {
        $data = request()->all();
        $signature = $data['signature'] ?? '';
        unset($data['signature']);
        $expectedSignature = $this->epusdtSign($data, $this->config['auth_token']);
        throw_if($signature !== $expectedSignature, new \Exception('Invalid signature'));
    }

    public function success()
    {
        return response('ok');
    }

    private function epusdtSign(array $parameter, string $signKey): string
    {
        ksort($parameter);

        $sign = '';
        foreach ($parameter as $key => $val) {
            if ($val == '') continue;
            if ($key != 'signature') {
                if ($sign != '') {
                    $sign .= "&";
                }
                $sign .= "$key=$val";
            }
        }

        return md5($sign . $signKey);
    }
}
