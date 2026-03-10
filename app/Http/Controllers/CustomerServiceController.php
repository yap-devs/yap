<?php

namespace App\Http\Controllers;

use App\Jobs\UpdateUserUuid;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        /** @var User $user */
        $user = $request->user();

        return DB::transaction(function () use ($user) {
            // Lock the user row to prevent concurrent balance modifications
            $user = User::lockForUpdate()->find($user->id);
            $price = config('yap.reset_subscription_price');

            if ($user->balance < $price) {
                return redirect()->route('customer.service')
                    ->withErrors([
                        'error' => 'Insufficient balance to reset subscription.',
                    ]);
            }

            UpdateUserUuid::dispatch($user);

            $user->decrement('balance', $price);
            $user->balanceDetails()->create([
                'amount' => -$price,
                'description' => 'Subscription URL reset',
            ]);

            return redirect()->route('customer.service')
                ->withErrors([
                    'success' => 'Subscription reset successfully, please wait for a few minutes for the changes to take effect.',
                ]);
        });
    }
}
