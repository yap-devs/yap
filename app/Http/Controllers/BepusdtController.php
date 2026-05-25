<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\User;
use App\Services\BepusdtService;
use App\Services\PaymentFulfillmentService;
use App\Services\RechargeOrderLockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Random\RandomException;
use Throwable;

class BepusdtController extends Controller
{
    /**
     * @return RedirectResponse
     *
     * @throws RandomException
     * @throws Throwable
     */
    public function newOrder(Request $request, BepusdtService $bepusdtService, RechargeOrderLockService $rechargeOrderLockService)
    {
        $request->validate([
            'amount' => 'required|numeric|min:2|max:100',  // in USD
        ]);

        /** @var User $user */
        $user = $request->user();

        $amount = $request->input('amount');

        return $rechargeOrderLockService->create($user, function () use ($amount, $bepusdtService, $user) {
            $out_trade_no = 'U'.time().random_int(10000, 99999);
            $info = $bepusdtService->createTransaction($amount, $out_trade_no);

            $payment = $user->payments()->create([
                'gateway' => Payment::GATEWAY_USDT,
                'status' => Payment::STATUS_CREATED,
                'amount' => $amount,
                'remote_id' => $out_trade_no,
                'payload' => [
                    Payment::STATUS_CREATED => $info,
                ],
            ]);

            return redirect()->route('bepusdt.scan', compact('payment'));
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

        if ($payment->gateway !== Payment::GATEWAY_USDT) {
            return redirect()->route('recharge')->withErrors([
                'message' => __('messages.errors.invalid_payment_gateway'),
            ]);
        }

        $info = $payment->payload[Payment::STATUS_CREATED] ?? null;
        $payment_url = Arr::get($info, 'data.payment_url');

        if (! $payment_url) {
            return redirect()->route('recharge')->withErrors([
                'message' => __('messages.errors.payment_url_not_found'),
            ]);
        }

        return Inertia::location($payment_url);
    }

    /**
     * @throws Throwable
     */
    public function notify(Request $request, BepusdtService $bepusdtService, PaymentFulfillmentService $paymentFulfillmentService)
    {
        $bepusdtService->callback();

        if ($request->input('status') != BepusdtService::STATUS_SUCCESS) {
            logger()->warning('Bepusdt payment status is not success: '.$request->getContent());

            return $bepusdtService->success();
        }

        $payment = Payment::where('remote_id', $request->input('order_id'))->first();
        if (! $payment) {
            logger()->warning('Bepusdt payment not found: '.$request->input('order_id'));

            return $bepusdtService->success();
        }

        $paymentFulfillmentService->fulfill($payment, $request->all());

        return $bepusdtService->success();
    }
}
