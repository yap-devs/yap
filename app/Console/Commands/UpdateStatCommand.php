<?php

namespace App\Console\Commands;

use App\Jobs\GenerateClashProfileLink;
use App\Models\User;
use App\Models\UserPackage;
use App\Models\VmessServer;
use App\Services\V2rayService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class UpdateStatCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-stat-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update traffic stats for users';

    private $user_status_changed = false;

    /**
     * Execute the console command.
     *
     * @throws Throwable
     */
    public function handle()
    {
        $users = User::with(['packages' => function ($query) {
            $query->where('status', UserPackage::STATUS_ACTIVE);
        }])->get();
        $vmess_servers = VmessServer::where('enabled', true)->get();
        $all_stats = $this->getAllStats($vmess_servers);

        /** @var User $user */
        foreach ($users as $user) {
            $total_uplink = 0;
            $total_downlink = 0;
            $is_valid_initial = $this->checkIsValid($user);
            $is_low_priority_initial = $user->is_low_priority;

            // Track which internal_servers have been counted to avoid double-counting
            // when multiple vmess_servers share the same internal_server
            $counted_internal_servers = [];

            /** @var VmessServer $vmess_server */
            foreach ($vmess_servers as $vmess_server) {
                if (isset($counted_internal_servers[$vmess_server->internal_server])) {
                    continue;
                }
                $counted_internal_servers[$vmess_server->internal_server] = true;

                $stats = Arr::get($all_stats, $vmess_server->id, []);

                if (! $stats || ! isset($stats['user'][$user->email])) {
                    continue;
                }

                $user_stat = $stats['user'][$user->email];
                $uplink = Arr::get($user_stat, 'uplink', 0) * $vmess_server->rate;
                $downlink = Arr::get($user_stat, 'downlink', 0) * $vmess_server->rate;

                $total_uplink += $uplink;
                $total_downlink += $downlink;
            }

            if (($total_uplink > 0) || ($total_downlink > 0)) {
                // Use direct attribute mutation instead of increment() to keep
                // the in-memory model in sync with what we write to DB. increment()
                // issues a raw UPDATE that does not update the model attributes,
                // which would cause billUser() to operate on stale data.
                $user->traffic_uplink += $total_uplink;
                $user->traffic_downlink += $total_downlink;
                $user->traffic_unpaid += $total_uplink + $total_downlink;
                $user->save();

                $user->stats()->create([
                    'traffic_uplink' => $total_uplink,
                    'traffic_downlink' => $total_downlink,
                ]);
            }

            $this->billUser($user);

            // Reload the eager-loaded packages after billing may have changed statuses
            $user->load(['packages' => function ($query) {
                $query->where('status', UserPackage::STATUS_ACTIVE);
            }]);

            if (
                $this->checkIsValid($user) != $is_valid_initial
                || $user->is_low_priority != $is_low_priority_initial
            ) {
                $this->user_status_changed = true;
            }

            Cache::forget('today_traffic_'.$user->id);
        }

        if (now()->hour == 0) {
            $this->updateBalanceDaily($users);
        }

        if ($this->user_status_changed) {
            GenerateClashProfileLink::dispatchSync();
        }
    }

    /**
     * Check if user is valid using the eager-loaded packages relation
     * instead of the is_valid accessor which triggers a DB query each time.
     */
    private function checkIsValid(User $user): bool
    {
        $balance = (float) $user->balance;
        $github_created_at = $user->github_created_at;

        return $balance > 0
            || $user->packages->isNotEmpty()
            || ($github_created_at !== null && $github_created_at->diffInYears(now()) - 9 > abs($balance));
    }

    /**
     * Bill user for traffic: deduct from packages first, then from balance.
     * Wrapped in a transaction with a row lock to prevent concurrent payment
     * webhooks from losing balance credits.
     */
    private function billUser(User $user): void
    {
        DB::transaction(function () use ($user) {
            // Re-fetch with lock to get the latest balance (a payment webhook
            // may have credited balance since we loaded the user) and to prevent
            // concurrent modifications from overwriting each other.
            /** @var User $locked_user */
            $locked_user = User::lockForUpdate()->find($user->id);

            $this->expirePackage($locked_user);

            while (
                $locked_user->packages()->where('status', UserPackage::STATUS_ACTIVE)->exists()
                && $locked_user->traffic_unpaid > 0
            ) {
                $locked_user->traffic_unpaid = $this->processPackage($locked_user, $locked_user->traffic_unpaid);
                $locked_user->save();
            }

            while ($locked_user->traffic_unpaid > 1024 * 1024 * 1024) {
                $locked_user->balance -= config('yap.unit_price');
                $locked_user->traffic_unpaid -= 1024 * 1024 * 1024;
            }

            if ($locked_user->isDirty(['balance', 'traffic_unpaid'])) {
                $locked_user->balanceDetails()->create([
                    'amount' => $locked_user->balance - $locked_user->getOriginal('balance'),
                    'description' => 'Traffic deduction',
                ]);

                $this->log("User $locked_user->email balance updated from {$locked_user->getOriginal('balance')} to $locked_user->balance");
                $locked_user->save();
            }

            // Sync the outer model so subsequent reads (is_valid, is_low_priority)
            // reflect the changes made inside the transaction.
            $user->fill($locked_user->getAttributes());
            $user->syncOriginal();
        });
    }

    private function expirePackage(User $user)
    {
        $user->packages()
            ->where('status', UserPackage::STATUS_ACTIVE)
            ->where('ended_at', '<', now())
            ->update(['status' => UserPackage::STATUS_EXPIRED]);
    }

    private function processPackage(User $user, $traffic)
    {
        /** @var UserPackage $user_package */
        $user_package = $user->packages()
            ->where('status', UserPackage::STATUS_ACTIVE)
            ->orderBy('ended_at')
            ->first();

        if (! $user_package) {
            return $traffic;
        }

        if ($user_package->remaining_traffic < $traffic) {
            $traffic -= $user_package->remaining_traffic;
            $user_package->remaining_traffic = 0;
            $user_package->status = UserPackage::STATUS_USED;
            $user_package->save();

            return $traffic;
        }

        $user_package->remaining_traffic -= $traffic;
        $user_package->save();

        return 0;
    }

    private function getAllStats($vmess_servers)
    {
        $stats = [];

        // Deduplicate by internal_server to avoid SSHing into the same server twice.
        // Multiple vmess_servers may share the same internal_server (different entry points).
        // The first getStats(reset: true) would drain the counters, leaving nothing for the second call.
        // handle() also skips duplicate internal_servers when accumulating traffic, so we only
        // need to store stats under the first vmess_server_id for each internal_server.
        $fetched_internal_servers = [];

        /** @var VmessServer $vmess_server */
        foreach ($vmess_servers as $vmess_server) {
            if (isset($fetched_internal_servers[$vmess_server->internal_server])) {
                continue;
            }
            $fetched_internal_servers[$vmess_server->internal_server] = true;

            try {
                $v2ray = new V2rayService($vmess_server->internal_server);
                $stats[$vmess_server->id] = $v2ray->getStats(reset: true);
            } catch (Throwable $e) {
                logger()->driver('job')->log(
                    'error',
                    "[UpdateStatCommand] Failed to get stats from server: {$vmess_server->internal_server}, error: {$e->getMessage()}"
                );
            }
        }

        return $stats;
    }

    /**
     * Settle remaining unpaid traffic for users who have sub-GB usage
     * and no active packages. Runs once daily at midnight.
     *
     * @param  Collection  $users
     */
    private function updateBalanceDaily($users)
    {
        /** @var User $user */
        foreach ($users as $user) {
            // if never used, skip (in-memory value is current after billUser synced it)
            if ($user->traffic_unpaid == 0) {
                continue;
            }

            // if have active package, skip (uses eager-loaded relation, no extra query)
            $user->load(['packages' => function ($query) {
                $query->where('status', UserPackage::STATUS_ACTIVE);
            }]);
            if ($user->packages->isNotEmpty()) {
                continue;
            }

            $is_valid = $this->checkIsValid($user);

            DB::transaction(function () use ($user) {
                // Re-fetch with lock to get latest balance and prevent concurrent
                // payment webhooks from losing their credits.
                /** @var User $locked_user */
                $locked_user = User::lockForUpdate()->find($user->id);

                // if already settled recently, skip
                if ($locked_user->last_settled_at && $locked_user->last_settled_at->diffInHours(now()) < 23.5) {
                    return;
                }

                // Re-check traffic_unpaid from DB in case billUser() already settled it
                if ($locked_user->traffic_unpaid == 0) {
                    return;
                }

                $locked_user->balance -= config('yap.unit_price');
                $locked_user->traffic_unpaid = 0;
                $locked_user->balanceDetails()->create([
                    'amount' => $locked_user->balance - $locked_user->getOriginal('balance'),
                    'description' => 'Daily deduction',
                ]);
                $this->log("User $locked_user->email balance updated from {$locked_user->getOriginal('balance')} to $locked_user->balance");
                $locked_user->save();

                // Sync outer model
                $user->fill($locked_user->getAttributes());
                $user->syncOriginal();
            });

            if ($this->checkIsValid($user) != $is_valid) {
                $this->user_status_changed = true;
            }
        }
    }

    private function log($message, $level = 'info')
    {
        $message = '[UpdateStatCommand] '.$message;
        logger()->driver('job')->log($level, $message);
    }
}
