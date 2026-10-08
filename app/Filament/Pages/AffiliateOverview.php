<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AffiliateOverviewStats;

class AffiliateOverview extends Dashboard
{
    protected static bool $isDiscovered = true;

    protected static ?string $title = 'Affiliate Overview';

    protected static string $routePath = 'affiliate-overview';

    protected static string|\UnitEnum|null $navigationGroup = 'Affiliates';

    protected static ?int $navigationSort = 0;

    public function getSubheading(): ?string
    {
        return 'Qualification comes from eligible paid top-ups. Commissions come from package purchases and are credited after the hold period. All account IDs are included.';
    }

    public function getWidgets(): array
    {
        return [AffiliateOverviewStats::class];
    }
}
