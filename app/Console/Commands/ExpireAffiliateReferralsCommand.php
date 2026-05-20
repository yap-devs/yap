<?php

namespace App\Console\Commands;

use App\Services\Affiliate\AffiliateService;
use Illuminate\Console\Command;

class ExpireAffiliateReferralsCommand extends Command
{
    protected $signature = 'affiliate:expire-referrals';

    protected $description = 'Expire affiliate referrals after their commission window';

    public function handle(AffiliateService $affiliateService): int
    {
        $expired = $affiliateService->expireReferrals();
        $this->info("Expired {$expired} affiliate referrals.");

        return self::SUCCESS;
    }
}
