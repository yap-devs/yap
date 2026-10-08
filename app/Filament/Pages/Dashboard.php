<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\OperationsWorkspace;
use App\Services\AdminDashboardReportService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static bool $isDiscovered = false;

    protected static ?string $title = 'Operations Overview';

    public static function canAccess(): bool
    {
        return auth()->id() === 1;
    }

    public function getSubheading(): ?string
    {
        return 'Check today’s activity, system status, and items to follow up.';
    }

    public static function getPollingIntervalOptions(): array
    {
        return [
            '60s' => 'Every 60 seconds',
            '5m' => 'Every 5 minutes',
            'off' => 'Manual refresh only',
        ];
    }

    public function getColumns(): int|array
    {
        return [
            'md' => 8,
            'xl' => 8,
        ];
    }

    public function getWidgets(): array
    {
        return [OperationsWorkspace::class];
    }

    public function content(Schema $schema): Schema
    {
        if (static::class !== self::class) {
            return parent::content($schema);
        }

        return $schema->components([
            $this->getWidgetsContentComponent(),
            Section::make('Refresh settings')->collapsed()->schema([$this->getFiltersFormContentComponent()]),
        ]);
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->columns([
                'md' => 2,
            ])
            ->components([
                Select::make('polling_interval')
                    ->label('Auto Refresh')
                    ->options(static::getPollingIntervalOptions())
                    ->default('60s')
                    ->native(false)
                    ->selectablePlaceholder(false),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshDashboard')
                ->label('Refresh now')
                ->icon('heroicon-m-arrow-path')
                ->labeledFrom('sm')
                ->color('primary')
                ->action('refreshDashboard'),
        ];
    }

    public function refreshDashboard(): void
    {
        app(AdminDashboardReportService::class)->clearDashboardCache();
        $this->dispatch('dashboard-refresh');
    }
}
