<?php

namespace App\Services;

use App\Models\TrafficBatch;
use App\Models\TrafficRecord;
use App\Models\UserStat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TrafficAggregationService
{
    public function aggregate(): int
    {
        return Cache::lock('node-traffic:aggregate', 300)->get(function (): int {
            $processed = 0;
            $deadline = microtime(true) + 30;
            $cutoff = now()->startOfHour();
            while (microtime(true) < $deadline) {
                $count = DB::transaction(function () use ($cutoff): int {
                    $batches = TrafficBatch::whereNull('aggregated_at')->where('received_at', '<', $cutoff)
                        ->orderBy('id')->limit(20)->lockForUpdate()->get();
                    if ($batches->isEmpty()) {
                        return 0;
                    }
                    foreach ($batches->groupBy(fn (TrafficBatch $batch): string => $batch->received_at->startOfHour()->toDateTimeString()) as $hour => $hour_batches) {
                        $totals = TrafficRecord::whereIn('traffic_batch_id', $hour_batches->modelKeys())
                            ->selectRaw('user_id, SUM(billed_uplink) AS uplink, SUM(billed_downlink) AS downlink')
                            ->groupBy('user_id')->get();
                        foreach ($totals as $total) {
                            $stat = UserStat::where('user_id', $total->user_id)->where('created_at', $hour)->lockForUpdate()->first();
                            if (! $stat) {
                                $stat = new UserStat(['user_id' => $total->user_id]);
                                $stat->created_at = $hour;
                                $stat->traffic_uplink = 0;
                                $stat->traffic_downlink = 0;
                            }
                            $stat->traffic_uplink += $total->uplink;
                            $stat->traffic_downlink += $total->downlink;
                            $stat->save();
                            Cache::forget(UserStat::todayTrafficCacheKey($total->user_id));
                        }
                    }
                    TrafficBatch::whereIn('id', $batches->modelKeys())->update(['aggregated_at' => now()]);

                    return $batches->count();
                }, 3);
                $processed += $count;
                if ($count === 0) {
                    break;
                }
            }

            return $processed;
        }) ?: 0;
    }
}
