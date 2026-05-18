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

        if (! config('services.sub2api.enabled') && $user->sub2api_key_id) {
            return redirect()->route('customer.service')
                ->with('error', __('messages.errors.ai_subscription_reset_unavailable'));
        }

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
                    ->with('error', __('messages.errors.subscription_reset_in_progress'));
            }

            if ($user->balance < $price) {
                return redirect()->route('customer.service')
                    ->with('error', __('messages.errors.insufficient_reset_balance'));
            }

            $user->decrement('balance', $price);
            $user->balanceDetails()->create([
                'amount' => -$price,
                'description' => __('messages.balance_descriptions.subscription_url_reset', [], 'en'),
            ]);

            Cache::put($pending_key, true, now()->addMinutes(15));
            UpdateUserUuid::dispatch($user, $old_key_id, $old_uuid, $new_uuid)->afterCommit();

            return redirect()->route('customer.service')
                ->with('success', __('messages.success.subscription_reset'));
        });
    }
}
