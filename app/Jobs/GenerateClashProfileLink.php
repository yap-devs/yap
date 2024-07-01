<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\VmessServer;
use App\Services\ClashService;
use App\Services\V2rayService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class GenerateClashProfileLink implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $vmess_servers;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     * @throws Throwable
     */
    public function handle()
    {
        $users = User::withTrashed()->get();
        $this->vmess_servers = VmessServer::all();

        foreach ($users as $user) {
            $clash = new ClashService($user, $this->vmess_servers);

            if ($user->deleted_at || !$user->is_valid) {
                $this->log("User $user->email is invalid, removing...", 'warning');
                $this->removeUser($user);
                $clash->delConf();
                continue;
            }

            $this->log("Generating subscription link for user $user->email");
            /** @var VmessServer $vmess_server */
            foreach ($this->vmess_servers as $vmess_server) {
                $v2ray = new V2rayService($vmess_server->internal_server);
                $v2ray->addUser($user->email, $user->uuid);
                $this->log("Added user $user->email to V2ray server $vmess_server->internal_server");
            }

            $clash->genConf();
            $this->log("Generated: " . storage_path("clash-config/$user->uuid.yaml"));
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
        /** @var VmessServer $vmess_server */
        foreach ($this->vmess_servers as $vmess_server) {
            $v2ray = new V2rayService($vmess_server->internal_server);
            $v2ray->removeUser($user->email);
            $this->log("Removed user $user->email from V2ray server $vmess_server->internal_server");
        }
    }

    private function log($message, $level = 'info')
    {
        $message = '[GenerateClashProfileLink] ' . $message;
        logger()->driver('job')->log($level, $message);
    }
}
