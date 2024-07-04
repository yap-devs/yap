<?php

namespace App\Observers;

use App\Models\User;

class UserObserver
{
    /**
     * Handle the User "updating" event.
     */
    public function updating(User $user): void
    {
        // if balance updated, update last_settled_at
        if ($user->isDirty('balance')) {
            $user->last_settled_at = now();
        }
    }
}
