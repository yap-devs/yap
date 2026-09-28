<?php

namespace App\Filament\Widgets\Concerns;

use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Livewire\Attributes\On;

trait InteractsWithDashboardControls
{
    use InteractsWithPageFilters;

    protected function getTrendWindowMonths(): int
    {
        $months = (int) ($this->pageFilters['trend_window'] ?? 12);

        return in_array($months, [6, 12, 24], true) ? $months : 12;
    }

    protected function getTrendWindowLabel(): string
    {
        return 'last '.$this->getTrendWindowMonths().' months';
    }

    protected function getPollingInterval(): ?string
    {
        $interval = $this->pageFilters['polling_interval'] ?? '60s';

        return match ($interval) {
            'off' => null,
            '5m' => '5m',
            default => '60s',
        };
    }

    public function updatedPageFilters(): void
    {
        $this->clearDashboardWidgetCaches();
    }

    #[On('dashboard-refresh')]
    public function refreshDashboardWidget(): void
    {
        $this->clearDashboardWidgetCaches();
    }

    protected function clearDashboardWidgetCaches(): void
    {
        if (property_exists($this, 'cachedData')) {
            $this->cachedData = null;
        }

        if (property_exists($this, 'cachedStats')) {
            $this->cachedStats = null;
        }

        if (method_exists($this, 'flushCachedTableRecords')) {
            $this->flushCachedTableRecords();
        }
    }
}
