<?php

namespace App\Console\Commands;

use App\Services\TrafficAggregationService;
use Illuminate\Console\Command;

class AggregateNodeTraffic extends Command
{
    protected $signature = 'nodes:aggregate-traffic';

    protected $description = 'Aggregate committed node traffic into hourly display statistics';

    public function handle(TrafficAggregationService $service): int
    {
        if (! config('node_agent.enabled')) {
            return self::SUCCESS;
        }
        $this->info('Aggregated '.$service->aggregate().' batches.');

        return self::SUCCESS;
    }
}
