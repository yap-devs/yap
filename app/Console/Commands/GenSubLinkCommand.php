<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\VmessServer;
use App\Services\ClashService;
use App\Services\V2rayService;
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
    protected $description = 'Generate subscription link for all users.';

    /**
     * Execute the console command.
     * @throws Throwable
     */
    public function handle()
    {
        $users = User::all()->filter(fn(User $user) => $user->is_valid)->values();
        $vmess_servers = VmessServer::all();

        foreach ($users as $user) {
            $this->info("Generating subscription link for user $user->email");
            /** @var VmessServer $vmess_server */
            foreach ($vmess_servers as $vmess_server) {
                $v2ray = new V2rayService($vmess_server->internal_server);
                $v2ray->addUser($user->email, $user->uuid);
                $this->info("Added user $user->email to V2ray server $vmess_server->internal_server");
            }

            $clash = new ClashService($user);
            $clash->genConf();
            $this->info("Generated: " . storage_path("clash-config/$user->uuid.yaml"));
        }
    }
}
