<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ClashService;
use Illuminate\Console\Command;

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
    protected $description = 'Generate subscription link for all users.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $users = User::all()->filter(fn(User $user) => $user->is_valid)->values();

        foreach ($users as $user) {
            (new ClashService($user))->genConf();
        }
    }
}
