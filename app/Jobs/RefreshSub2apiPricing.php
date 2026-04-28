<?php

namespace App\Jobs;

use App\Services\Sub2apiPricingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RefreshSub2apiPricing implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(Sub2apiPricingService $sub2api_pricing_service): void
    {
        $guide = $sub2api_pricing_service->refreshPricingGuideIfMissing();

        if ($guide['available']) {
            $sub2api_pricing_service->releaseRefreshReservation();
        }
    }

    public function failed(?Throwable $exception): void
    {
        // Keep the short reservation TTL as backoff when the upstream request fails.
    }
}
