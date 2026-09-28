<?php

namespace App\Console\Commands;

use App\Services\TrafficReportSnapshotService;
use Illuminate\Console\Command;

class RefreshTrafficReportCommand extends Command
{
    protected $signature = 'app:refresh-traffic-report';

    protected $description = 'Rebuild the dashboard traffic snapshot from collected traffic';

    public function handle(TrafficReportSnapshotService $snapshots): int
    {
        if (! $snapshots->refresh()) {
            $this->warn('A traffic report refresh is already running.');

            return self::FAILURE;
        }

        $this->info('Traffic report snapshot refreshed.');

        return self::SUCCESS;
    }
}
