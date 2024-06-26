<?php

namespace App\Console\Commands;

use App\Jobs\GenerateClashProfileLink;
use App\Models\User;
use Illuminate\Console\Command;

class UpdateBalanceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-balance-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update user balance based on traffic_unpaid';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $users = User::all();

        /** @var User $user */
        foreach ($users as $user) {
            $this->info("Updating balance for user $user->email");
            while ($user->traffic_unpaid > 1024 * 1024 * 1024) {
                $user->balance -= config('yap.unit_price');
                $user->traffic_unpaid -= 1024 * 1024 * 1024;
            }

            if ($user->isDirty(['balance', 'traffic_unpaid'])) {
                $this->info("User $user->email balance updated from {$user->getOriginal('balance')} to $user->balance");
                $user->save();
            }
        }

        GenerateClashProfileLink::dispatchSync();
    }
}
