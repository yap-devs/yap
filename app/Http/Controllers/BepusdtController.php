<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateClashProfileLink;
use App\Models\Payment;
use App\Models\User;
use App\Services\BepusdtService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Random\RandomException;
use Throwable;

class BepusdtController extends Controller
{
    /**
     * @param Request $request
     * @param BepusdtService $bepusdtService
     * @return RedirectResponse
     * @throws RandomException
     * @throws Throwable
     */
    public function newOrder(Request $request, BepusdtService $bepusdtService)
    {
        $request->validate([
            'amount' => 'required|numeric|min:2',  // in USD
        ]);

        /** @var User $user */
        $user = $request->user();

        if ($user->payments()->where('status', Payment::STATUS_CREATED)->exists()) {
            return redirect()->route('profile.edit')->withErrors([
                'message' => 'You have an unpaid payment.'
            ]);
        }

        $amount = $request->input('amount');
        $out_trade_no = 'U' . time() . random_int(10000, 99999);

        $info = $bepusdtService->createTransaction($amount, $out_trade_no);

        $payment = $user->payments()->create([
            'gateway' => Payment::GATEWAY_USDT,
            'status' => 'created',
            'amount' => $amount,
            'remote_id' => $out_trade_no,
            'payload' => [
                Payment::STATUS_CREATED => $info
            ]
        ]);

        return redirect()->route('bepusdt.scan', compact('payment'));
    }

    public function scan(Request $request, Payment $payment)
    {
        /** @var User $user */
        $user = $request->user();
        if ($payment->user->isNot($user)) {
            return redirect()->route('profile.edit')->withErrors([
                'message' => 'Payment not found.'
            ]);
        }

        if ($payment->gateway !== Payment::GATEWAY_USDT) {
            return redirect()->route('profile.edit')->withErrors([
                'message' => 'Invalid payment gateway.'
            ]);
        }

        $info = $payment->payload[Payment::STATUS_CREATED] ?? null;
        $payment_url = Arr::get($info, 'data.payment_url');

        if (!$payment_url) {
            return redirect()->route('profile.edit')->withErrors([
                'message' => 'Payment URL not found.'
            ]);
        }

        return redirect()->away($payment_url);
    }

    /**
     * @throws Throwable
     */
    public function notify(Request $request, BepusdtService $bepusdtService)
    {
        $bepusdtService->callback();

        if ($request->input('status') != BepusdtService::STATUS_SUCCESS) {
            logger()->warning('Bepusdt payment status is not success: ' . $request->getContent());
            return $bepusdtService->success();
        }

        $payment = Payment::where('remote_id', $request->input('order_id'))->first();
        if (!$payment) {
            logger()->warning('Bepusdt payment not found: ' . $request->input('order_id'));
            return $bepusdtService->success();
        }

        if ($payment->status === Payment::STATUS_PAID) {
            logger()->warning('Bepusdt payment already paid: ' . $payment->remote_id);
            return $bepusdtService->success();
        }

        $payment->status = Payment::STATUS_PAID;
        $payload = $payment->payload;
        $payload[Payment::STATUS_PAID] = $request->all();
        $payment->payload = $payload;
        $payment->save();

        $payment->user->increment('balance', $payment->amount);

        $payment->user->balanceDetails()->create([
            'amount' => $payment->amount,
            'description' => 'Usdt payment',
        ]);

        GenerateClashProfileLink::dispatch();

        return $bepusdtService->success();
    }
}
