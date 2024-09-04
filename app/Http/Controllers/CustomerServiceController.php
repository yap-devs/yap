<?php

namespace App\Http\Controllers;

use App\Jobs\UpdateUserUuid;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CustomerServiceController extends Controller
{
    public function index()
    {
        $resetSubscriptionPrice = config('yap.reset_subscription_price');

        return Inertia::render('CustomerService/Index', compact('resetSubscriptionPrice'));
    }

    public function resetSubscription(Request $request)
    {
        if ($request->user()->balance < config('yap.reset_subscription_price')) {
            return redirect()->route('customer.service')
                ->withErrors([
                    'error' => 'Insufficient balance to reset subscription.',
                ]);
        }

        UpdateUserUuid::dispatch($request->user());

        $request->user()->decrement('balance', config('yap.reset_subscription_price'));
        $request->user()->balanceDetails()->create([
            'amount' => -config('yap.reset_subscription_price'),
            'description' => 'Subscription URL reset',
        ]);

        return redirect()->route('customer.service')
            ->withErrors([
                'success' => 'Subscription reset successfully, please wait for a few minutes for the changes to take effect.',
            ]);
    }
}
