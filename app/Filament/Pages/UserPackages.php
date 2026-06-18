<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\UserPackagesOverviewWidget;
use App\Filament\Widgets\UserPackagesTable;
use App\Models\UserPackage;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;

class UserPackages extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $title = 'User Packages';

    protected static string $routePath = 'user-packages';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 4;

    public function getSubheading(): ?string
    {
        return 'Package subscriptions, ended-package profit, and per-user package details.';
    }

    public function getColumns(): int|array
    {
        return [
            'md' => 12,
            'xl' => 12,
        ];
    }

    public function getWidgets(): array
    {
        return [
            UserPackagesOverviewWidget::class,
            UserPackagesTable::class,
        ];
    }

    public function getWidgetData(): array
    {
        return [];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->columns([
                'md' => 2,
            ])
            ->components([
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'ended' => 'Ended (expired + used)',
                        UserPackage::STATUS_ACTIVE => 'Active',
                        UserPackage::STATUS_EXPIRED => 'Expired',
                        UserPackage::STATUS_USED => 'Used',
                        UserPackage::STATUS_DISABLED => 'Disabled',
                        'all' => 'All packages',
                    ])
                    ->default('ended')
                    ->native(false)
                    ->selectablePlaceholder(false)
                    ->helperText('Stats and package table use this same status filter.'),
            ]);
    }
}
