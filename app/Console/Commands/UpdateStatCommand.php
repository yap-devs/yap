<?php

namespace App\Console\Commands;

use App\Jobs\GenerateClashProfileLink;
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

    private $user_status_changed = false;

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
                    $this->log("User $email not found", 'warning');
                    continue;
                }

                $is_valid = $user->is_valid;

                $user->increment('traffic_uplink', $uplink);
                $user->increment('traffic_downlink', $downlink);
                $user->increment('traffic_unpaid', $uplink + $downlink);

                UserStat::create([
                    'user_id' => $user->id,
                    'server_id' => $vmess_server->id,
                    'traffic_uplink' => $uplink,
                    'traffic_downlink' => $downlink,
                ]);

                while ($user->traffic_unpaid > 1024 * 1024 * 1024) {
                    $user->balance -= config('yap.unit_price');
                    $user->traffic_unpaid -= 1024 * 1024 * 1024;
                }

                if ($user->isDirty(['balance', 'traffic_unpaid'])) {
                    $this->log("[$vmess_server->name] User $user->email balance updated from {$user->getOriginal('balance')} to $user->balance");
                    $user->save();
                }

                if ($user->is_valid != $is_valid) {
                    $this->user_status_changed = true;
                }
            }
        }

        if (now()->hour == 0) {
            $this->updateBalanceDaily();
        }

        if ($this->user_status_changed) {
            GenerateClashProfileLink::dispatchSync();
        }
    }

    private function updateBalanceDaily()
    {
        $users = User::all();

        /** @var User $user */
        foreach ($users as $user) {
            $is_valid = $user->is_valid;
            if ($user->traffic_unpaid > 0) {
                $user->balance -= config('yap.unit_price');
                $user->traffic_unpaid = 0;
                $user->save();
                $this->log("User $user->email balance updated from {$user->getOriginal('balance')} to $user->balance");

                if ($user->is_valid != $is_valid) {
                    $this->user_status_changed = true;
                }
            }
        }
    }

    private function log($message, $level = 'info')
    {
        $message = '[UpdateStatCommand] ' . $message;
        logger()->driver('job')->log($level, $message);
    }
}
