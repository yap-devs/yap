<?php

namespace App\Services;

use Illuminate\Auth\SessionGuard;
use Illuminate\Contracts\Auth\Authenticatable;

class RememberTokenSessionGuard extends SessionGuard
{
    public function getName(): string
    {
        return 'login_'.$this->name.'_'.sha1(SessionGuard::class);
    }

    public function getRecallerName(): string
    {
        return 'remember_'.$this->name.'_'.sha1(SessionGuard::class);
    }

    /**
     * Reject revoked tokens before comparing the password hash.
     */
    protected function userFromRecaller(mixed $recaller): ?Authenticatable
    {
        if (! $recaller->valid() || $this->recallAttempted) {
            return null;
        }

        $this->recallAttempted = true;
        $user = $this->provider->retrieveByToken($recaller->id(), $recaller->token());
        if ($user === null) {
            return null;
        }

        $password = (string) $user->getAuthPassword();
        $cookie_hash = $recaller->hash();
        $this->viaRemember = hash_equals($this->hashPasswordForCookie($password), $cookie_hash)
            || hash_equals($password, $cookie_hash);

        return $this->viaRemember ? $user : null;
    }
}
