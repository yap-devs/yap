<?php

namespace App\Console\Commands;

use App\Jobs\UpdateUserUuid;
use App\Models\User;
use Illuminate\Console\Command;

class UpdateUserUuidCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-user-uuid-command {user}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update user uuid';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $user_id = $this->argument('user');
        $user = User::findOrFail($user_id);

        UpdateUserUuid::dispatchSync($user);

        $user = $user->first();
        $this->info('User UUID updated successfully: [' . $user->uuid . '] ' . $user->email);
    }
}
