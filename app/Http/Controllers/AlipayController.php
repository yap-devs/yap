<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentFulfillmentService;
use App\Services\RechargeOrderLockService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Random\RandomException;
use Yansongda\Artful\Exception\ContainerException;
use Yansongda\Artful\Exception\Exception as ArtfulException;
use Yansongda\Artful\Exception\InvalidParamsException;
use Yansongda\Artful\Exception\ServiceNotFoundException;
use Yansongda\LaravelPay\Facades\Pay;
use Yansongda\Pay\Exception\Exception as PayException;

class AlipayController extends Controller
{
    /** @noinspection PhpRedundantCatchClauseInspection */
    public function notify(PaymentFulfillmentService $paymentFulfillmentService)
    {
        try {
            $result = Pay::alipay()->callback();
        } catch (ArtfulException|PayException $e) {
            logger()->error('Alipay notify failed: '.$e->getMessage());

            return Pay::alipay()->success();
        }

        if ($result->get('trade_status') !== 'TRADE_SUCCESS') {
            logger()->warning('Alipay trade status is not TRADE_SUCCESS: '.$result->toJson());

            return Pay::alipay()->success();
        }

        $out_trade_no = $result->get('out_trade_no');
        $payment = Payment::where('remote_id', $out_trade_no)->first();
        if (! $payment) {
            logger()->warning('Alipay payment not found: '.$out_trade_no);

            return Pay::alipay()->success();
        }

        $paymentFulfillmentService->fulfill($payment, $result->toArray());

        return Pay::alipay()->success();
    }

    /**
     * @throws ServiceNotFoundException
     * @throws ContainerException
     * @throws InvalidParamsException
     */
    public function query(Request $request, Payment $payment)
    {
        /** @var User $user */
        $user = $request->user();
        if ($payment->user->isNot($user)) {
            return redirect()->route('recharge')->withErrors([
                'message' => __('messages.errors.payment_not_found'),
            ]);
        }

        $result = Pay::alipay()->query([
            'out_trade_no' => $payment->remote_id,
        ]);

        return response()->json($result);
    }

    /**
     * @throws RandomException
     */
    public function newOrder(Request $request, RechargeOrderLockService $rechargeOrderLockService)
    {
        $request->validate([
            'amount' => 'required|numeric|min:2|max:100',  // in USD
        ]);

        /** @var User $user */
        $user = $request->user();

        $amount = $request->input('amount');

        return $rechargeOrderLockService->create($user, function () use ($amount, $user) {
            $out_trade_no = time().random_int(100000, 999999);

            $qr_info = Pay::alipay()->scan([
                'out_trade_no' => $out_trade_no,
                'total_amount' => bcmul($amount, config('yap.payment.usd_rmb_rate'), 2),
                'subject' => config('yap.payment.alipay.subject'),
            ]);

            if ($qr_info['code'] !== '10000') {
                logger()->critical('Alipay scan failed: '.json_encode($qr_info));

                return redirect()->route('recharge')->withErrors([
                    'message' => __('messages.errors.alipay_create_failed'),
                ]);
            }

            /** @var Payment $payment */
            $payment = $user->payments()->create([
                'gateway' => Payment::GATEWAY_ALIPAY,
                'status' => Payment::STATUS_CREATED,
                'amount' => $amount,
                'remote_id' => $out_trade_no,
                'payload' => [
                    Payment::STATUS_CREATED => $qr_info,
                ],
            ]);

            return redirect()->route('alipay.scan', compact('payment'));
        });
    }

    public function scan(Request $request, Payment $payment)
    {
        /** @var User $user */
        $user = $request->user();
        if ($payment->user->isNot($user)) {
            return redirect()->route('recharge')->withErrors([
                'message' => __('messages.errors.payment_not_found'),
            ]);
        }

        if ($payment->gateway !== Payment::GATEWAY_ALIPAY) {
            return redirect()->route('recharge')->withErrors([
                'message' => __('messages.errors.invalid_payment_gateway'),
            ]);
        }

        return Inertia::render('Payment/Alipay/Scan', [
            'QRInfo' => $payment->payload[Payment::STATUS_CREATED],
            'amount' => $payment->amount,
            'paymentId' => $payment->id,
        ]);
    }
}
