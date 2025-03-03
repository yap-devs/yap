<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserPackage;
use App\Notifications\PackageExpireReminder;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PackageExpireRemindCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:package-expire-remind-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remind users of their package expiration.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $users = User::all();

        foreach ($users as $user) {
            $latest_active_user_package = $user->packages()
                ->where('status', UserPackage::STATUS_ACTIVE)
                ->orderBy('ended_at', 'desc')
                ->first();

            if ($latest_active_user_package && Carbon::parse($latest_active_user_package->ended_at)->isTomorrow()) {
                logger()->driver('job')->info("User $user->id will have their package expire tomorrow, sending email to remind them.");
                // Send email to remind user of package expiration
                $user->notify(new PackageExpireReminder($user));
            }
        }
    }
}
