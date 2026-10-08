<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TrafficBillingService;
use App\Services\TrafficReportSnapshotService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
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
    protected $description = 'Settle account traffic and refresh dashboard summaries';

    /**
     * Execute the console command.
     *
     * @throws Throwable
     */
    public function handle(): void
    {
        Cache::lock('account-traffic-maintenance', 1800)->block(10, fn () => $this->maintainAccounts());
    }

    private function maintainAccounts(): void
    {
        User::with('packages')->chunkById(100, function ($users): void {
            foreach ($users as $user) {
                app(TrafficBillingService::class)->settle($user);
            }
            if (now()->hour === 0 && now()->minute < 10) {
                $this->updateBalanceDaily($users);
            }
        });
        try {
            app(TrafficReportSnapshotService::class)->refresh();
        } catch (Throwable $exception) {
            logger()->error('Failed to refresh the dashboard traffic snapshot.', ['exception' => $exception]);
        }
    }

    /**
     * Settle remaining unpaid traffic for users who have sub-GB usage
     * and no active packages. Runs once daily at midnight.
     *
     * @param  Collection  $users
     */
    private function updateBalanceDaily($users): void
    {
        /** @var User $user */
        foreach ($users as $user) {
            // if never used, skip (in-memory value is current after settlement)
            if ($user->traffic_unpaid == 0) {
                continue;
            }

            // if have active package, skip (uses eager-loaded relation, no extra query)
            $user->load(['packages' => function ($query) {
                $query->available();
            }]);
            if ($user->packages->isNotEmpty()) {
                continue;
            }

            DB::transaction(function () use ($user) {
                // Re-fetch with lock to get latest balance and prevent concurrent
                // payment webhooks from losing their credits.
                /** @var User $locked_user */
                $locked_user = User::lockForUpdate()->find($user->id);

                // if already settled recently, skip
                if ($locked_user->last_settled_at && $locked_user->last_settled_at->diffInHours(now()) < 23.5) {
                    return;
                }

                // Re-check traffic_unpaid from DB in case another settlement already processed it
                if ($locked_user->traffic_unpaid == 0) {
                    return;
                }

                $locked_user->balance -= config('yap.unit_price');
                $locked_user->traffic_unpaid = 0;
                $locked_user->balanceDetails()->create([
                    'amount' => $locked_user->balance - $locked_user->getOriginal('balance'),
                    'description' => __('messages.balance_descriptions.daily_deduction', [], 'en'),
                ]);
                $this->log("User $locked_user->email balance updated from {$locked_user->getOriginal('balance')} to $locked_user->balance");
                $locked_user->save();

                // Sync outer model
                $user->fill($locked_user->getAttributes());
                $user->syncOriginal();
            });

        }
    }

    private function log(string $message, string $level = 'info'): void
    {
        $message = '[UpdateStatCommand] '.$message;
        logger()->driver('job')->log($level, $message);
    }
}
