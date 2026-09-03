<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserPackage;
use App\Notifications\PackageLowTrafficReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class PackageLowTrafficNotificationCommand extends Command
{
    private const LOW_TRAFFIC_THRESHOLD = 0.10;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:package-low-traffic-notification-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify users weekly when their active package is low on traffic.';

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
                    /** @var UserPackage|null $latest_active_user_package */
                    $latest_active_user_package = $user->packages->first();
                    $total_traffic = $latest_active_user_package?->package?->traffic_limit ?? 0;

                    if ($total_traffic <= 0) {
                        continue;
                    }

                    $remaining_traffic = $latest_active_user_package->remaining_traffic;
                    $remaining_percentage = $remaining_traffic / $total_traffic;

                    if ($remaining_percentage > self::LOW_TRAFFIC_THRESHOLD) {
                        continue;
                    }

                    $cache_key = sprintf(
                        'package-low-traffic-reminder:%d:%d:%s',
                        $user->getKey(),
                        $latest_active_user_package->getKey(),
                        now('Asia/Tokyo')->startOfWeek()->toDateString(),
                    );

                    if (! Cache::add($cache_key, true, now('Asia/Tokyo')->endOfWeek()->endOfDay())) {
                        continue;
                    }

                    try {
                        $percent_remaining = round($remaining_percentage * 100);
                        logger()->driver('job')->info("User $user->id is running low on package traffic ($percent_remaining% remaining), queueing email notification.");
                        $user->notify(new PackageLowTrafficReminder(
                            $remaining_traffic,
                            $total_traffic,
                            $remaining_percentage,
                        ));
                    } catch (Throwable $exception) {
                        Cache::forget($cache_key);

                        throw $exception;
                    }
                }
            });

        return self::SUCCESS;
    }
}
