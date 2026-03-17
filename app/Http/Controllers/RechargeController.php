<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RechargeController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $pending_payment = $user->payments()
            ->where('status', Payment::STATUS_CREATED)
            ->latest()
            ->first(['id', 'gateway', 'amount', 'status', 'created_at']);

        return Inertia::render('Recharge/Index', [
            'githubSponsorURL' => config('yap.github.sponsor_url'),
            'stripeSandbox' => str_starts_with(config('yap.payment.stripe.secret'), 'sk_test_'),
            'pendingPayment' => $pending_payment,
        ]);
    }

    public function cancel(Request $request, Payment $payment): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_if($payment->user_id !== $user->id, 404);
        abort_if($payment->status !== Payment::STATUS_CREATED, 404);

        $payment->status = Payment::STATUS_CANCELLED;
        $payment->save();

        return redirect()->route('recharge');
    }
}
