<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AtRiskPackagesTable;
use App\Filament\Widgets\AtRiskUsersTable;
use App\Filament\Widgets\PackageUtilizationHealthChart;
use App\Filament\Widgets\UserAccessHealthChart;
use App\Filament\Widgets\UserActivityWidget;

class AccessHealth extends Dashboard
{
    protected static bool $isDiscovered = true;

    protected static ?string $title = 'Access Health';

    protected static string $routePath = 'access-health';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Customers';

    protected static ?int $navigationSort = 3;

    public function getSubheading(): ?string
    {
        return 'Users without an available package and packages close to their traffic limit.';
    }

    public function getColumns(): int|array
    {
        return ['md' => 12, 'xl' => 12];
    }

    public function getWidgets(): array
    {
        return [
            UserActivityWidget::class,
            UserAccessHealthChart::class,
            PackageUtilizationHealthChart::class,
            AtRiskUsersTable::class,
            AtRiskPackagesTable::class,
        ];
    }
}
