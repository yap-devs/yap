<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\AccessHealth;
use App\Filament\Widgets\Concerns\InteractsWithDashboardControls;
use App\Services\AdminDashboardReportService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AttentionRequiredWidget extends StatsOverviewWidget
{
    use InteractsWithDashboardControls;

    protected int|string|array $columnSpan = 'full';

    protected int|array|null $columns = ['@xl' => 2, '!@lg' => 2];

    protected ?string $heading = 'Needs Attention';

    protected function getStats(): array
    {
        $reports = app(AdminDashboardReportService::class);
        $users = $reports->getAccessHealthBreakdown();
        $packages = $reports->getPackageUtilizationBreakdown();

        return [
            Stat::make('Users without package, balance < $1',
                number_format((int) $users->get('Low balance', 0) + (int) $users->get('Negative balance', 0)))
                ->description(number_format((int) $users->get('Negative balance', 0)).' have a negative balance')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('warning')
                ->url(AccessHealth::getUrl()),
            Stat::make('Packages under 10% traffic', number_format((int) $packages->get('Critical <10%', 0)))
                ->description('Active, started packages')
                ->descriptionIcon('heroicon-m-cube')
                ->color('danger')
                ->url(AccessHealth::getUrl()),
        ];
    }
}
