<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateClashProfileLink;
use App\Models\Payment;
use App\Models\User;
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
    public function notify()
    {
        try {
            $result = Pay::alipay()->callback();
        } catch (ArtfulException|PayException $e) {
            logger()->error('Alipay notify failed: ' . $e->getMessage());
            return Pay::alipay()->success();
        }

        if ($result->get('trade_status') !== 'TRADE_SUCCESS') {
            logger()->warning('Alipay trade status is not TRADE_SUCCESS: ' . $result->toJson());
            return Pay::alipay()->success();
        }

        $out_trade_no = $result->get('out_trade_no');
        $payment = Payment::where('remote_id', $out_trade_no)->first();
        if (!$payment) {
            logger()->warning('Alipay payment not found: ' . $out_trade_no);
            return Pay::alipay()->success();
        }
        if ($payment->status === Payment::STATUS_PAID) {
            logger()->warning('Alipay payment already paid: ' . $out_trade_no);
            return Pay::alipay()->success();
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
            return redirect()->route('profile.edit')->withErrors([
                'message' => 'Payment not found.'
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
    public function newOrder(Request $request)
    {
        $request->validate([
            'amount' => 'required|integer|min:5|max:100',  // in USD
        ]);

        /** @var User $user */
        $user = $request->user();

        if ($user->payments()->where('status', Payment::STATUS_CREATED)->exists()) {
            return redirect()->route('profile.edit')->withErrors([
                'message' => 'You have an unpaid payment.'
            ]);
        }

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

        /** @var Payment $payment */
        $payment = $user->payments()->create([
            'gateway' => 'alipay',
            'status' => 'created',
            'amount' => $amount,
            'remote_id' => $out_trade_no,
            'payload' => [
                Payment::STATUS_CREATED => $qr_info
            ]
        ]);

        return redirect()->route('alipay.scan', compact('payment'));
    }

    /**
     * @throws RandomException
     */
    public function scan(Request $request, Payment $payment)
    {
        /** @var User $user */
        $user = $request->user();
        if ($payment->user->isNot($user)) {
            return redirect()->route('profile.edit')->withErrors([
                'message' => 'Payment not found.'
            ]);
        }

        if ($payment->gateway !== Payment::GATEWAY_ALIPAY) {
            return redirect()->route('profile.edit')->withErrors([
                'message' => 'Invalid payment gateway.'
            ]);
        }

        return Inertia::render('Payment/Alipay/Scan', [
            'QRInfo' => $payment->payload[Payment::STATUS_CREATED],
            'amount' => $payment->amount,
            'paymentId' => $payment->id,
        ]);
    }
}
