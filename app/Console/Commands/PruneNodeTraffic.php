<?php

namespace App\Console\Commands;

use App\Models\TrafficBatch;
use App\Models\TrafficRecord;
use Illuminate\Console\Command;

class PruneNodeTraffic extends Command
{
    protected $signature = 'nodes:prune-traffic';

    protected $description = 'Purge expired aggregated traffic details while retaining retry receipts';

    public function handle(): int
    {
        if (! config('node_agent.enabled')) {
            return self::SUCCESS;
        }
        $cutoff = now()->subDays(max(1, (int) config('node_agent.record_retention_days')));
        $deadline = microtime(true) + 20;
        $count = 0;
        do {
            $ids = TrafficRecord::withTrashed()->where('created_at', '<', $cutoff)
                ->whereIn('traffic_batch_id', TrafficBatch::withTrashed()->select('id')->whereNotNull('aggregated_at'))
                ->orderBy('id')->limit(1000)->pluck('id');
            $count += TrafficRecord::withTrashed()->whereIn('id', $ids)->forceDelete();
        } while ($ids->isNotEmpty() && microtime(true) < $deadline);
        $this->info('Pruned '.$count.' traffic records.');

        return self::SUCCESS;
    }
}
