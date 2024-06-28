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

    private $vmess_servers;

    /**
     * Execute the console command.
     * @throws Throwable
     */
    public function handle()
    {
        $users = User::withTrashed()->get();
        $this->vmess_servers = VmessServer::all();

        foreach ($users as $user) {
            $clash = new ClashService($user, $this->vmess_servers);

            if ($user->deleted_at || !$user->is_valid) {
                $this->warn("User $user->email is invalid, removing...");
                $this->removeUser($user);
                $clash->delConf();
                continue;
            }

            $this->info("Generating subscription link for user $user->email");
            /** @var VmessServer $vmess_server */
            foreach ($this->vmess_servers as $vmess_server) {
                $v2ray = new V2rayService($vmess_server->internal_server);
                $v2ray->addUser($user->email, $user->uuid);
                $this->info("Added user $user->email to V2ray server $vmess_server->internal_server");
            }

            $clash->genConf();
            $this->info("Generated: " . storage_path("clash-config/$user->uuid.yaml"));
        }
    }

    /**
     * Removes a user from all V2ray servers.
     *
     * @param User $user The user entity to be removed
     * @return void
     * @throws Throwable
     */
    private function removeUser(User $user)
    {
        foreach ($this->vmess_servers as $vmess_server) {
            $v2ray = new V2rayService($vmess_server->internal_server);
            $v2ray->removeUser($user->email);
            $this->info("Removed user $user->email from V2ray server $vmess_server->internal_server");
        }
    }
}
