<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\AffiliateCommissionResource;
use App\Filament\Resources\AffiliatePromoterResource;
use App\Filament\Resources\AffiliateReferralResource;
use App\Filament\Widgets\Concerns\InteractsWithDashboardControls;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePromoter;
use App\Models\AffiliateReferral;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AffiliateOverviewStats extends StatsOverviewWidget
{
    use InteractsWithDashboardControls;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $pending = AffiliateCommission::where('status', AffiliateCommission::STATUS_PENDING);

        return [
            Stat::make('Active promoters', AffiliatePromoter::where('status', AffiliatePromoter::STATUS_ACTIVE)->count())->url(AffiliatePromoterResource::getUrl()),
            Stat::make('Qualified referrals', AffiliateReferral::whereNotNull('qualified_at')->whereIn('status', [AffiliateReferral::STATUS_QUALIFIED, AffiliateReferral::STATUS_EARNING, AffiliateReferral::STATUS_EXPIRED])->count())->url(AffiliateReferralResource::getUrl()),
            Stat::make('Pending commissions', '$'.number_format((float) (clone $pending)->sum('amount'), 2))->description('Awaiting hold period and eligibility checks')->url(AffiliateCommissionResource::getUrl('index', ['tableFilters' => ['status' => ['value' => 'pending']]])),
            Stat::make('Due for processing', (clone $pending)->where('hold_until', '<=', now())->count())->description('The scheduled settlement rechecks eligibility')->url(AffiliateCommissionResource::getUrl('index', ['tableFilters' => ['due' => ['isActive' => true]]])),
            Stat::make('Credited commissions', '$'.number_format((float) AffiliateCommission::where('status', AffiliateCommission::STATUS_CREDITED)->sum('amount'), 2))->url(AffiliateCommissionResource::getUrl('index', ['tableFilters' => ['status' => ['value' => 'credited']]])),
        ];
    }
}
