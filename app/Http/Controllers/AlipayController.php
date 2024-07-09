<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Psr\Http\Message\ResponseInterface;
use Random\RandomException;
use Yansongda\Artful\Exception\ContainerException;
use Yansongda\Artful\Exception\InvalidParamsException;
use Yansongda\Artful\Exception\ServiceNotFoundException;
use Yansongda\LaravelPay\Facades\Pay;

class AlipayController extends Controller
{
    /**
     * @return ResponseInterface
     * @throws ContainerException
     * @throws InvalidParamsException
     */
    public function notify()
    {
        $result = Pay::alipay()->callback();

        logger()->info('Alipay notify: ' . $result->toJson());

        return Pay::alipay()->success();
    }

    /**
     * @throws ServiceNotFoundException
     * @throws ContainerException
     * @throws InvalidParamsException
     */
    public function query(Request $request, int $paymentId)
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Payment $payment */
        $payment = $user->payments()->findOrFail($paymentId);

        $result = Pay::alipay()->query([
            'out_trade_no' => $payment->remote_id,
        ]);

        return response()->json($result);
    }

    /**
     * @throws RandomException
     */
    public function scan(Request $request)
    {
        if ($request->method() !== 'POST') {
            return redirect()->route('profile.edit')->withErrors([
                'message' => 'Invalid request, how dare you.'
            ]);
        }

        $request->validate([
            'amount' => 'required|integer|min:5|max:100',  // in USD
        ]);

        /** @var User $user */
        $user = $request->user();

        $out_trade_no = time() . random_int(100000, 999999);
        $amount = $request->input('amount');

        $qr_info = Pay::alipay()->scan([
            'out_trade_no' => $out_trade_no,
            'total_amount' => bcmul($amount, config('yap.payment.usd_rmb_rate'), 2),
            'subject' => config('yap.payment.alipay.subject'),
        ]);

        if ($qr_info['code'] !== '10000') {
            logger()->critical('Alipay scan failed: ' . json_encode($qr_info));
            return redirect()->route('profile.edit')->withErrors([
                'message' => 'Failed to create Alipay payment.'
            ]);
        }

        $payment = $user->payments()->create([
            'gateway' => 'alipay',
            'status' => 'created',
            'amount' => $amount,
            'remote_id' => $out_trade_no,
            'payload' => [
                'created' => $qr_info
            ]
        ]);

        return Inertia::render('Payment/Alipay/Scan', [
            'QRInfo' => $qr_info,
            'amount' => $amount,
            'paymentId' => $payment->id,
        ]);
    }
}
