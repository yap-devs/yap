<?php

namespace App\Console\Commands;

use App\Services\Affiliate\AffiliateService;
use Illuminate\Console\Command;

class CreditPendingAffiliateCommissionsCommand extends Command
{
    protected $signature = 'affiliate:credit-pending-commissions';

    protected $description = 'Credit due affiliate commissions';

    public function handle(AffiliateService $affiliateService): int
    {
        $credited = $affiliateService->creditPendingCommissions();
        $this->info("Credited {$credited} affiliate commissions.");

        return self::SUCCESS;
    }
}
