<?php

namespace App\Http\Controllers;

use App\Jobs\UpdateUserUuid;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
            $old_key_id = $user->sub2api_key_id;
            $old_uuid = $user->uuid;
            $new_uuid = (string) Str::uuid();
            $pending_key = UpdateUserUuid::pendingCacheKey($user->id);

            if (Cache::has($pending_key)) {
                return redirect()->route('customer.service')
                    ->withErrors([
                        'error' => 'A subscription reset is already in progress. Please wait a few minutes and try again.',
                    ]);
            }

            if ($user->balance < $price) {
                return redirect()->route('customer.service')
                    ->withErrors([
                        'error' => 'Insufficient balance to reset subscription.',
                    ]);
            }

            $user->decrement('balance', $price);
            $user->balanceDetails()->create([
                'amount' => -$price,
                'description' => 'Subscription URL reset',
            ]);

            Cache::put($pending_key, true, now()->addMinutes(15));
            UpdateUserUuid::dispatch($user, $old_key_id, $old_uuid, $new_uuid)->afterCommit();

            return redirect()->route('customer.service')
                ->withErrors([
                    'success' => 'Subscription reset successfully, please wait for a few minutes for the changes to take effect.',
                ]);
        });
    }
}
