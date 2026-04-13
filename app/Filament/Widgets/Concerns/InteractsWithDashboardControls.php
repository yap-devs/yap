<?php

namespace App\Filament\Widgets\Concerns;

use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Livewire\Attributes\On;

trait InteractsWithDashboardControls
{
    use InteractsWithPageFilters;

    protected function getTrendWindowMonths(): int
    {
        return max((int) ($this->pageFilters['trend_window'] ?? 12), 1);
    }

    protected function getTrendWindowLabel(): string
    {
        return 'last '.$this->getTrendWindowMonths().' months';
    }

    protected function getPollingInterval(): ?string
    {
        $interval = $this->pageFilters['polling_interval'] ?? '15s';

        return $interval === 'off' ? null : $interval;
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
