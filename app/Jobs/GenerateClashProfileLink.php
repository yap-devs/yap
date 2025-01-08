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
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $this->vmess_servers = VmessServer::where('enabled', true)->get();

        $result = [];
        $users = User::withTrashed()->get();
        foreach ($users as $user) {
            $result[] = $this->preProcessUser($user);
        }
        $this->processV2Ray($result);
    }

    /**
     * Processes a user for V2ray and Clash services.
     *
     * @param User $user The user entity to be processed
     * @return array [$user, array] [$user, servers belonging to the user]
     */
    private function preProcessUser(User $user): array
    {
        $clash = new ClashService($user);

        if (
            // user deleted
            $user->deleted_at
            // or user is not valid and no active packages
            || (!$user->is_valid && $user->packages()->where('status', UserPackage::STATUS_ACTIVE)->doesntExist())
        ) {
            if (!$clash->confExists()) {
                return [$user, []];
            }

            $clash->delConf();
            return [$user, []];
        }

        $servers = [];
        /** @var VmessServer $vmess_server */
        foreach ($this->vmess_servers as $vmess_server) {
            if ($user->is_low_priority && !$vmess_server->for_low_priority) {
                continue;
            }

            $servers[] = $vmess_server;
        }

        $clash->genConf($servers);
        return [$user, $servers];
    }

    private function processV2Ray(array $result)
    {
        // ['internal_server' => $users]
        $server_user_map = [];
        foreach ($result as $item) {
            /** @var User $user */
            /** @var VmessServer $servers */
            [$user, $servers] = $item;
            if (empty($servers)) {
                continue;
            }

            foreach ($servers as $server) {
                $server_user_map[$server->internal_server][] = [
                    'id' => $user->uuid,
                    'email' => $user->email,
                ];
            }
        }

        foreach ($server_user_map as $internal_server => $users) {
            $v2ray = new V2rayService($internal_server);
            $v2ray->addOrRemoveUsers($users);
        }
    }
}
