<?php

namespace App\Observers;

use App\Models\User;
use App\Notifications\BalanceReminder;

class UserObserver
{
    /**
     * Handle the User "updating" event.
     */
    public function updating(User $user): void
    {
        // if balance decreased, update last_settled_at
        if ($user->isDirty('balance') && $user->balance < $user->getOriginal('balance')) {
            $user->last_settled_at = now();

            if ($user->balance < config('yap.balance_reminder_threshold')) {
                $user->notify(new BalanceReminder($user));
            }
        }
    }
}
