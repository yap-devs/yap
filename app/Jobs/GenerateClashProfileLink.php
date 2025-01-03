<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\UserPackage;
use App\Models\VmessServer;
use App\Services\ClashService;
use App\Services\V2rayService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateClashProfileLink implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $vmess_servers;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ?User $user = null
    )
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $this->vmess_servers = VmessServer::all();

        if (!is_null($this->user)) {
            $this->processUser($this->user);
            return;
        }

        $users = User::withTrashed()->get();
        foreach ($users as $user) {
            $this->processUser($user);
        }
    }

    /**
     * Processes a user for V2ray and Clash services.
     *
     * @param User $user The user entity to be processed
     * @return void
     */
    private function processUser(User $user)
    {
        $clash = new ClashService($user);

        if (
            // user deleted
            $user->deleted_at
            // or user is not valid and no active packages
            || (!$user->is_valid && $user->packages()->where('status', UserPackage::STATUS_ACTIVE)->doesntExist())
        ) {
            if (!$clash->confExists()) {
                return;
            }

            $this->log("User $user->email is invalid, removing...", 'warning');
            $this->removeUser($user);
            $clash->delConf();
            return;
        }

        $this->log("Generating subscription link for user $user->email");
        $servers = [];
        /** @var VmessServer $vmess_server */
        foreach ($this->vmess_servers as $vmess_server) {
            if ($user->is_low_priority && !$vmess_server->for_low_priority) {
                continue;
            }

            $v2ray = new V2rayService($vmess_server->internal_server);
            $res = $v2ray->addUser($user->email, $user->uuid);
            $this->log("Added user $user->email to V2ray server $vmess_server->internal_server: " . json_encode($res));

            $servers[] = $vmess_server;
        }

        $clash->genConf($servers);
        $this->log("Generated: " . storage_path("clash-config/$user->uuid.yaml"));
    }

    /**
     * Removes a user from all V2ray servers.
     *
     * @param User $user The user entity to be removed
     * @return void
     */
    private function removeUser(User $user)
    {
        /** @var VmessServer $vmess_server */
        foreach ($this->vmess_servers as $vmess_server) {
            $v2ray = new V2rayService($vmess_server->internal_server);
            $res = $v2ray->removeUser($user->email);
            $this->log("Removed user $user->email from V2ray server $vmess_server->internal_server: " . json_encode($res));
        }
    }

    private function log($message, $level = 'info')
    {
        $message = '[GenerateClashProfileLink] ' . $message;
        logger()->driver('job')->log($level, $message);
    }
}
