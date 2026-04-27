<?php

namespace App\Console\Commands;

use App\Services\Sub2apiPricingService;
use Illuminate\Console\Command;

class RefreshSub2apiPricingCommand extends Command
{
    protected $signature = 'app:refresh-sub2api-pricing-command';

    protected $description = 'Refresh cached AI model pricing';

    public function handle(Sub2apiPricingService $sub2api_pricing_service): int
    {
        $guide = $sub2api_pricing_service->refreshPricingGuide();

        if (! $guide['available']) {
            $this->warn('AI model pricing is unavailable.');

            return self::FAILURE;
        }

        $this->info('Cached pricing for '.count($guide['models']).' AI models.');

        return self::SUCCESS;
    }
}
