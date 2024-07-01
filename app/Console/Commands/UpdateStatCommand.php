<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserStat;
use App\Models\VmessServer;
use App\Services\V2rayService;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Throwable;

class UpdateStatCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-stat-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update traffic stats for users';

    /**
     * Execute the console command.
     * @throws Throwable
     */
    public function handle()
    {
        $vmess_servers = VmessServer::all();

        /** @var VmessServer $vmess_server */
        foreach ($vmess_servers as $vmess_server) {
            $v2ray = new V2rayService($vmess_server->internal_server);
            $stats = $v2ray->stats(reset: true);

            if (!$stats || !isset($stats['user'])) {
                continue;
            }

            $user_stats = $stats['user'];
            foreach ($user_stats as $email => $user_stat) {
                $uplink = Arr::get($user_stat, 'uplink', 0) * $vmess_server->rate;
                $downlink = Arr::get($user_stat, 'downlink', 0) * $vmess_server->rate;
                if (!$uplink && !$downlink) {
                    continue;
                }

                $user = User::where('email', $email)->first();
                if (!$user) {
                    continue;
                }

                $user->increment('traffic_uplink', $uplink);
                $user->increment('traffic_downlink', $downlink);
                $user->increment('traffic_unpaid', $uplink + $downlink);

                UserStat::create([
                    'user_id' => $user->id,
                    'server_id' => $vmess_server->id,
                    'traffic_uplink' => $uplink,
                    'traffic_downlink' => $downlink,
                ]);

                $this->info("[$vmess_server->name]Updated traffic stats for user $email, uplink: $uplink, downlink: $downlink");
            }
        }
    }
}
