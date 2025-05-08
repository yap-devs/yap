<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserPackage;
use App\Notifications\PackageExpireReminder;
use App\Notifications\PackageLowTrafficReminder;
use Carbon\Carbon;
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
    protected $description = 'Notify users about package status (expiration and low traffic).';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $users = User::all();
        // Define the threshold for low traffic warning (10%)
        $lowTrafficThreshold = 0.10;

        foreach ($users as $user) {
            $latest_active_user_package = $user->packages()
                ->where('status', UserPackage::STATUS_ACTIVE)
                ->orderBy('ended_at', 'desc')
                ->first();

            if (!$latest_active_user_package) {
                continue;
            }

            // Check if package expires tomorrow
            if (Carbon::parse($latest_active_user_package->ended_at)->isTomorrow()) {
                logger()->driver('job')->info("User $user->id will have their package expire tomorrow, sending email to remind them.");
                // Send email to remind user of package expiration
                $user->notify(new PackageExpireReminder($user));
            }

            // Check if package is running low on traffic
            if ($latest_active_user_package->package && $latest_active_user_package->package->traffic_limit > 0) {
                $totalTraffic = $latest_active_user_package->package->traffic_limit;
                $remainingTraffic = $latest_active_user_package->remaining_traffic;
                $remainingPercentage = $remainingTraffic / $totalTraffic;

                if ($remainingPercentage <= $lowTrafficThreshold) {
                    $percentRemaining = round($remainingPercentage * 100);
                    logger()->driver('job')->info("User $user->id is running low on package traffic ($percentRemaining% remaining), sending email to notify them.");
                    // Send email to notify user of low remaining traffic
                    $user->notify(new PackageLowTrafficReminder($user, $latest_active_user_package, $remainingPercentage));
                }
            }
        }
    }
}
