<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateClashProfileLink;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Random\RandomException;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Stripe;
use Stripe\Webhook;

class StripeController extends Controller
{
    public function __construct()
    {
        Stripe::setApiKey(config('yap.payment.stripe.secret'));
    }

    /**
     * @throws RandomException
     * @throws ApiErrorException
     */
    public function newOrder(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:2|max:100',  // in USD
        ]);

        /** @var User $user */
        $user = $request->user();

        if ($user->payments()->where('status', Payment::STATUS_CREATED)->exists()) {
            return redirect()->route('profile.edit')->withErrors([
                'message' => 'You have an unpaid payment.',
            ]);
        }

        $out_trade_no = 'S'.time().random_int(100000, 999999);
        $amount = $request->input('amount');

        // Create payment record first so we have the ID for the success URL
        /** @var Payment $payment */
        $payment = $user->payments()->create([
            'gateway' => Payment::GATEWAY_STRIPE,
            'status' => Payment::STATUS_CREATED,
            'amount' => $amount,
            'remote_id' => $out_trade_no,
            'payload' => [
                Payment::STATUS_CREATED => [],
            ],
        ]);

        try {
            $session = Session::create([
                // Let Stripe determine available payment methods based on Dashboard settings,
                // currency (USD), and customer location. This enables card, Alipay, WeChat Pay,
                // Link, Apple Pay, Google Pay, and other USD-compatible methods for JP business.
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => 'Yap Account Recharge',
                        ],
                        'unit_amount' => bcmul($amount, 100, 0), // Stripe uses cents
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => route('stripe.success', ['payment' => $payment->id]).'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('profile.edit'),
                'metadata' => [
                    'order_id' => $out_trade_no,
                ],
            ]);
        } catch (ApiErrorException $e) {
            logger()->critical('Stripe session creation failed: '.$e->getMessage());
            $payment->delete();

            return redirect()->route('profile.edit')->withErrors([
                'message' => 'Failed to create Stripe payment.',
            ]);
        }

        // Store session details in payment payload
        $payment->payload = [
            Payment::STATUS_CREATED => [
                'session_id' => $session->id,
                'checkout_url' => $session->url,
            ],
        ];
        $payment->save();

        // Return checkout URL for frontend redirect via Inertia location header.
        // Cannot use redirect()->away() because Inertia XHR cannot follow
        // cross-origin redirects (CORS blocks it).
        return Inertia::location($session->url);
    }

    public function pay(Request $request, Payment $payment)
    {
        /** @var User $user */
        $user = $request->user();
        abort_if($payment->user->isNot($user), 404);
        abort_if($payment->gateway !== Payment::GATEWAY_STRIPE, 404);

        if ($payment->status !== Payment::STATUS_CREATED) {
            return redirect()->route('profile.edit')->withErrors([
                'message' => 'Payment is no longer available.',
            ]);
        }

        $checkout_url = $payment->payload[Payment::STATUS_CREATED]['checkout_url'] ?? null;
        if (! $checkout_url) {
            return redirect()->route('profile.edit')->withErrors([
                'message' => 'Checkout session not found.',
            ]);
        }

        return Inertia::location($checkout_url);
    }

    public function success(Request $request, Payment $payment)
    {
        /** @var User $user */
        $user = $request->user();
        if ($payment->user->isNot($user)) {
            return redirect()->route('profile.edit')->withErrors([
                'message' => 'Payment not found.',
            ]);
        }

        // The actual payment confirmation happens via webhook.
        // This page just shows a thank-you message.
        return redirect()->route('profile.edit');
    }

    public function webhook(Request $request)
    {
        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');
        $webhook_secret = config('yap.payment.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sig_header, $webhook_secret);
        } catch (SignatureVerificationException $e) {
            logger()->error('Stripe webhook signature verification failed: '.$e->getMessage());

            return response('Invalid signature', 400);
        }

        $session = $event->data->object;

        // Handle immediate payment methods (card, Alipay, WeChat Pay, etc.)
        // payment_status is 'paid' right away for these.
        if ($event->type === 'checkout.session.completed' && $session->payment_status === 'paid') {
            $this->fulfillPayment($session);
        }

        // Handle delayed/async payment methods (SEPA debit, Bancontact, etc.)
        // checkout.session.completed fires with payment_status 'unpaid',
        // then async_payment_succeeded fires when the payment actually clears.
        if ($event->type === 'checkout.session.async_payment_succeeded') {
            $this->fulfillPayment($session);
        }

        // Handle failed async payments
        if ($event->type === 'checkout.session.async_payment_failed') {
            $order_id = $session->metadata->order_id ?? null;
            if ($order_id) {
                $payment = Payment::where('remote_id', $order_id)->first();
                if ($payment && $payment->status === Payment::STATUS_CREATED) {
                    $payment->status = Payment::STATUS_EXPIRED;
                    $payload = $payment->payload;
                    $payload['async_payment_failed'] = [
                        'session_id' => $session->id,
                    ];
                    $payment->payload = $payload;
                    $payment->save();
                }
            }
        }

        return response('ok');
    }

    /**
     * Credit user balance for a completed Stripe Checkout session.
     *
     * @throws \Throwable
     */
    private function fulfillPayment(object $session): void
    {
        $order_id = $session->metadata->order_id ?? null;

        if (! $order_id) {
            logger()->warning('Stripe webhook: missing order_id in metadata');

            return;
        }

        $payment = Payment::where('remote_id', $order_id)->first();
        if (! $payment) {
            logger()->warning('Stripe webhook: payment not found: '.$order_id);

            return;
        }

        DB::transaction(function () use ($payment, $session) {
            $payment = Payment::lockForUpdate()->find($payment->id);
            if ($payment->status === Payment::STATUS_PAID) {
                return;
            }

            $payment->status = Payment::STATUS_PAID;
            $payload = $payment->payload;
            $payload[Payment::STATUS_PAID] = [
                'session_id' => $session->id,
                'payment_intent' => $session->payment_intent,
                'payment_status' => $session->payment_status,
            ];
            $payment->payload = $payload;
            $payment->save();

            $payment->user->increment('balance', $payment->amount);

            $payment->user->balanceDetails()->create([
                'amount' => $payment->amount,
                'description' => 'Stripe payment',
            ]);

            GenerateClashProfileLink::dispatch();
        });
    }
}
