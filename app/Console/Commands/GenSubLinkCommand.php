<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;
use Throwable;

class GenSubLinkCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:gen-sub-link-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebuild subscription cache from node routes.';

    /**
     * Execute the console command.
     *
     * @throws Throwable
     */
    public function handle(): void
    {
        $subscriptions = app(SubscriptionService::class);
        User::withTrashed()->with('packages')->chunkById(100, function ($users) use ($subscriptions): void {
            foreach ($users as $user) {
                if ($user->trashed() || ! $user->is_valid) {
                    $subscriptions->forgetCache($user);
                } else {
                    $subscriptions->warmCache($user);
                }
            }
        });

        $this->info('Rebuilt subscription cache.');
    }
}
