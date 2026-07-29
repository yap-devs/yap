<?php

namespace App\Filament\Resources\AffiliateReferralCodes\Widgets;

use App\Models\AffiliateCommission;
use App\Models\AffiliateReferral;
use App\Models\AffiliateReferralCode;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ReferralCodeOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $active_system_codes = AffiliateReferralCode::query()
            ->where('type', AffiliateReferralCode::TYPE_SYSTEM)
            ->where('status', AffiliateReferralCode::STATUS_ACTIVE)
            ->count();
        $active_custom_codes = AffiliateReferralCode::query()
            ->where('type', AffiliateReferralCode::TYPE_CUSTOM)
            ->where('status', AffiliateReferralCode::STATUS_ACTIVE)
            ->count();
        $disabled_custom_codes = AffiliateReferralCode::query()
            ->where('type', AffiliateReferralCode::TYPE_CUSTOM)
            ->where('status', AffiliateReferralCode::STATUS_DISABLED)
            ->count();
        $cooling_codes = AffiliateReferralCode::query()
            ->where('type', AffiliateReferralCode::TYPE_CUSTOM)
            ->where('status', AffiliateReferralCode::STATUS_DISABLED)
            ->where('disabled_at', '>', now()->subHours((int) config('affiliate.referral_code_cooldown_hours')))
            ->count();
        $registrations = AffiliateReferral::query()->count();
        $valid_referrals = AffiliateReferral::query()
            ->whereIn('status', [
                AffiliateReferral::STATUS_QUALIFIED,
                AffiliateReferral::STATUS_EARNING,
                AffiliateReferral::STATUS_EXPIRED,
            ])
            ->whereNotNull('qualified_at')
            ->count();
        $credited_commission = (float) AffiliateCommission::query()
            ->where('status', AffiliateCommission::STATUS_CREDITED)
            ->sum('amount');

        return [
            Stat::make('Active Codes', number_format($active_system_codes + $active_custom_codes))
                ->description(number_format($active_custom_codes).' custom | '.number_format($active_system_codes).' system')
                ->descriptionIcon(Heroicon::OutlinedCheckCircle, IconPosition::Before)
                ->color('success'),
            Stat::make('Disabled Custom Codes', number_format($disabled_custom_codes))
                ->description(number_format($cooling_codes).' still occupying quota')
                ->descriptionIcon(Heroicon::OutlinedClock, IconPosition::Before)
                ->color($cooling_codes > 0 ? 'warning' : 'gray'),
            Stat::make('Registrations', number_format($registrations))
                ->description(number_format($valid_referrals).' valid referrals')
                ->descriptionIcon(Heroicon::OutlinedUsers, IconPosition::Before)
                ->color('info'),
            Stat::make('Credited Commission', number_format($credited_commission, 2).' USD')
                ->description('Completed affiliate earnings')
                ->descriptionIcon(Heroicon::OutlinedBanknotes, IconPosition::Before)
                ->color('primary'),
        ];
    }
}
