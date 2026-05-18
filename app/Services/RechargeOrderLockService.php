<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;

class RechargeOrderLockService
{
    public function create(User $user, Closure $callback): mixed
    {
        try {
            return Cache::lock($this->getLockKey($user), 10)->block(3, function () use ($user, $callback) {
                if ($user->payments()->where('status', Payment::STATUS_CREATED)->exists()) {
                    return $this->pendingPaymentRedirect();
                }

                return $callback();
            });
        } catch (LockTimeoutException) {
            return redirect()->route('recharge')->withErrors([
                'message' => __('messages.errors.recharge_order_locked'),
            ]);
        }
    }

    private function getLockKey(User $user): string
    {
        return 'recharge-order:create:user:'.$user->id;
    }

    private function pendingPaymentRedirect(): RedirectResponse
    {
        return redirect()->route('recharge')->withErrors([
            'message' => __('messages.errors.unpaid_payment_exists'),
        ]);
    }
}
