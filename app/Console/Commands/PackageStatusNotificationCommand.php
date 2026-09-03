<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\PackageExpireReminder;
use Illuminate\Console\Command;

class PackageStatusNotificationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:package-status-notification-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify users whose package expires tomorrow.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        User::query()
            ->with(['packages' => fn ($query) => $query
                ->available()
                ->orderByDesc('ended_at')
                ->limit(1)])
            ->chunkById(100, function ($users): void {
                /** @var User $user */
                foreach ($users as $user) {
                    $latest_active_user_package = $user->packages->first();

                    if (! $latest_active_user_package?->ended_at?->isTomorrow()) {
                        continue;
                    }

                    logger()->driver('job')->info("User $user->id will have their package expire tomorrow, queueing email reminder.");
                    $user->notify(new PackageExpireReminder);
                }
            });

        return self::SUCCESS;
    }
}
