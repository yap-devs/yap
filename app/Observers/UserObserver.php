<?php

namespace App\Observers;

use App\Models\User;
use App\Notifications\BalanceReminder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UserObserver
{
    const BR_DEBOUNCE_DAYS = 7;

    /**
     * Handle the User "updating" event.
     */
    public function updating(User $user): void
    {
        // if balance decreased, update last_settled_at
        if ($user->isDirty('balance') && $user->balance < $user->getOriginal('balance')) {
            $user->last_settled_at = now();

            if ($user->balance < config('yap.balance_reminder_threshold')) {
                $br_last_sent_at = Cache::get('balance_reminder_last_sent_at_'.$user->id);

                if ($br_last_sent_at && now()->diffInDays($br_last_sent_at) < self::BR_DEBOUNCE_DAYS) {
                    return;
                }

                DB::afterCommit(function () use ($user): void {
                    if (Cache::add('balance_reminder_last_sent_at_'.$user->id, now(), now()->addDays(self::BR_DEBOUNCE_DAYS))) {
                        $user->notify(new BalanceReminder($user));
                    }
                });
            }
        }
    }
}
