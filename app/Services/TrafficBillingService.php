<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserPackage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrafficBillingService
{
    /**
     * Bill user for traffic: deduct from packages first, then from balance.
     * Wrapped in a transaction with a row lock to prevent concurrent payment
     * webhooks from losing balance credits.
     */
    public function settle(User $user): void
    {
        DB::transaction(function () use ($user) {
            // Re-fetch with lock to get the latest balance (a payment webhook
            // may have credited balance since we loaded the user) and to prevent
            // concurrent modifications from overwriting each other.
            /** @var User $locked_user */
            $locked_user = User::lockForUpdate()->find($user->id);
            $locked_user->load(['packages' => fn ($query) => $query->active()->orderBy('id')->lockForUpdate()]);
            $this->settleLocked($locked_user);

            $user->fill($locked_user->getAttributes());
            $user->syncOriginal();
        });
    }

    /** Settle a user and active packages already locked by the caller's transaction. */
    public function settleLocked(User $locked_user): void
    {
        throw_if(DB::transactionLevel() === 0 || ! $locked_user->relationLoaded('packages'), \LogicException::class, 'Settlement requires a transaction and locked packages.');
        $this->expirePackage($locked_user);

        while (
            $locked_user->packages->contains(fn (UserPackage $package): bool => $package->status === UserPackage::STATUS_ACTIVE)
            && $locked_user->traffic_unpaid > 0
        ) {
            $locked_user->traffic_unpaid = $this->processPackage($locked_user, $locked_user->traffic_unpaid);
            $locked_user->save();
        }

        $gib = 1024 * 1024 * 1024;
        if ($locked_user->traffic_unpaid > $gib) {
            // Preserve the existing strict boundary: exactly one GiB remains unsettled.
            $units = intdiv((int) $locked_user->traffic_unpaid - 1, $gib);
            $unit_price = (string) config('yap.unit_price');
            if (! preg_match('/\A(?:0|[1-9][0-9]{0,5})(?:\.[0-9]{1,2})?\z/', $unit_price)) {
                throw ValidationException::withMessages(['records' => 'Traffic unit price must be a non-negative amount with at most two decimals.']);
            }
            $charge = bcmul((string) $units, $unit_price, 2);
            $balance = bcsub((string) $locked_user->balance, $charge, 2);
            if (bccomp($charge, '999999.99', 2) > 0 || bccomp($balance, '-999999.99', 2) < 0 || bccomp($balance, '999999.99', 2) > 0) {
                throw ValidationException::withMessages(['records' => 'Traffic charge exceeds the supported monetary range.']);
            }
            $locked_user->balance = $balance;
            $locked_user->traffic_unpaid -= $units * $gib;
        }

        if ($locked_user->isDirty(['balance', 'traffic_unpaid'])) {
            $locked_user->balanceDetails()->create([
                'amount' => bcsub((string) $locked_user->balance, (string) $locked_user->getOriginal('balance'), 2),
                'description' => __('messages.balance_descriptions.traffic_deduction', [], 'en'),
            ]);

            $locked_user->save();
        }
    }

    private function expirePackage(User $user): void
    {
        foreach ($user->packages as $package) {
            $package->setRelation('user', $user);
            if ($package->status === UserPackage::STATUS_ACTIVE && $package->ended_at && $package->ended_at->lessThanOrEqualTo(now())) {
                $package->status = UserPackage::STATUS_EXPIRED;
                $package->save();
            }
        }
    }

    private function processPackage(User $user, int $traffic): int
    {
        /** @var UserPackage $user_package */
        $user_package = $user->packages
            ->filter(fn (UserPackage $package): bool => $package->status === UserPackage::STATUS_ACTIVE)
            ->sortBy('ended_at')
            ->first();

        if (! $user_package) {
            return $traffic;
        }

        if (! $user_package->isStarted()) {
            $this->activateQueuedPackage($user_package);
        }

        if ($user_package->remaining_traffic <= $traffic) {
            $traffic -= $user_package->remaining_traffic;
            $user_package->remaining_traffic = 0;
            $user_package->status = UserPackage::STATUS_USED;
            $user_package->save();
            $this->activateNextQueuedPackage($user);

            return $traffic;
        }

        $user_package->remaining_traffic -= $traffic;
        $user_package->save();

        return 0;
    }

    private function activateNextQueuedPackage(User $user): void
    {
        /** @var UserPackage|null $next_package */
        $next_package = $user->packages
            ->filter(fn (UserPackage $package): bool => $package->isQueued())
            ->sortBy('started_at')
            ->first();

        if ($next_package) {
            $this->activateQueuedPackage($next_package);
        }
    }

    private function activateQueuedPackage(UserPackage $user_package): void
    {
        $started_at = CarbonImmutable::now();
        $original_started_at = CarbonImmutable::parse($user_package->started_at);
        $user_package->activateAt($started_at);
        $user_package->save();

        $next_started_at = $user_package->ended_at ? CarbonImmutable::parse($user_package->ended_at) : null;

        if ($next_started_at === null) {
            return;
        }

        $user_package->user->packages
            ->filter(fn (UserPackage $package): bool => $package->status === UserPackage::STATUS_ACTIVE
                && $package->id !== $user_package->id && $package->started_at && $package->started_at->greaterThan($original_started_at))
            ->sortBy('started_at')
            ->each(function (UserPackage $queued_package) use (&$next_started_at): void {
                if ($next_started_at === null) {
                    return;
                }

                $queued_package->activateAt($next_started_at);
                $queued_package->save();
                $next_started_at = $queued_package->ended_at ? CarbonImmutable::parse($queued_package->ended_at) : null;
            });
    }
}
