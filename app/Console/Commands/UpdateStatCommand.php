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
        $users = User::all();
        $vmess_servers = VmessServer::all();

        /** @var User $user */
        foreach ($users as $user) {
            $total_uplink = 0;
            $total_downlink = 0;
            $is_valid_initial = $user->is_valid;

            /** @var VmessServer $vmess_server */
            foreach ($vmess_servers as $vmess_server) {
                $v2ray = new V2rayService($vmess_server->internal_server);
                $stats = $v2ray->stats(reset: true);

                if (!$stats || !isset($stats['user'][$user->email])) {
                    continue;
                }

                $user_stat = $stats['user'][$user->email];
                $uplink = Arr::get($user_stat, 'uplink', 0) * $vmess_server->rate;
                $downlink = Arr::get($user_stat, 'downlink', 0) * $vmess_server->rate;

                $total_uplink += $uplink;
                $total_downlink += $downlink;

                UserStat::create([
                    'user_id' => $user->id,
                    'server_id' => $vmess_server->id,
                    'traffic_uplink' => $uplink,
                    'traffic_downlink' => $downlink,
                ]);
            }

            $user->increment('traffic_uplink', $total_uplink);
            $user->increment('traffic_downlink', $total_downlink);
            $user->increment('traffic_unpaid', $total_uplink + $total_downlink);

            while ($user->traffic_unpaid > 1024 * 1024 * 1024) {
                $user->balance -= config('yap.unit_price');
                $user->traffic_unpaid -= 1024 * 1024 * 1024;
            }

            if ($user->isDirty(['balance', 'traffic_unpaid'])) {
                $user->balanceDetails()->create([
                    'amount' => $user->balance - $user->getOriginal('balance'),
                    'description' => 'Traffic deduction',
                ]);

                $this->log("User $user->email balance updated from {$user->getOriginal('balance')} to $user->balance");
                $user->save();
            }

            if ($user->is_valid != $is_valid_initial) {
                $this->user_status_changed = true;
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
            // if already paid in the last 24 hours, skip
            if ($user->last_settled_at && $user->last_settled_at->diffInHours(now()) < 24) {
                continue;
            }

            // if never used, skip
            if ($user->traffic_unpaid == 0) {
                continue;
            }

            $is_valid = $user->is_valid;

            $user->balance -= config('yap.unit_price');
            $user->traffic_unpaid = 0;
            $user->balanceDetails()->create([
                'amount' => $user->balance - $user->getOriginal('balance'),
                'description' => 'Daily deduction',
            ]);
            $this->log("User $user->email balance updated from {$user->getOriginal('balance')} to $user->balance");
            $user->save();

            if ($user->is_valid != $is_valid) {
                $this->user_status_changed = true;
            }
        }
    }

    private function log($message, $level = 'info')
    {
        $message = '[UpdateStatCommand] ' . $message;
        logger()->driver('job')->log($level, $message);
    }
}
