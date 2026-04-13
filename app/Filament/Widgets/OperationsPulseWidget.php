<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\Concerns\InteractsWithDashboardControls;
use App\Services\AdminDashboardReportService;
use Carbon\CarbonImmutable;
use Filament\Widgets\Widget;

class OperationsPulseWidget extends Widget
{
    use InteractsWithDashboardControls;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.operations-pulse-widget';

    protected function getViewData(): array
    {
        $report = app(AdminDashboardReportService::class)->getOverviewStats($this->getTrendWindowMonths());
        $monthly_delta = round((float) $report['current_month_top_up'] - (float) $report['current_month_usage'], 2);

        return [
            'monthLabel' => CarbonImmutable::now()->format('F Y'),
            'trendWindowLabel' => $this->getTrendWindowLabel(),
            'pollingLabel' => Dashboard::getPollingIntervalOptions()[$this->pageFilters['polling_interval'] ?? '15s'] ?? 'Every 15 seconds',
            'currentMonthTopUp' => $this->formatCurrency((float) $report['current_month_top_up']),
            'currentMonthUsage' => $this->formatCurrency((float) $report['current_month_usage']),
            'monthlyDelta' => $this->formatCurrency(abs($monthly_delta)),
            'monthlyDeltaLabel' => $monthly_delta >= 0 ? 'Unconsumed cash-in this month' : 'Usage exceeded top-ups this month',
            'monthlyDeltaTone' => $monthly_delta >= 0 ? 'success' : 'danger',
            'outstandingBalance' => $this->formatCurrency((float) $report['outstanding_balance']),
            'packageBackedUsers' => number_format((int) $report['package_backed_user_count']),
            'atRiskUsers' => number_format((int) $report['access_at_risk_user_count']),
            'activePackages' => number_format((int) $report['active_package_count']),
            'remainingTraffic' => $this->formatGigabytes((float) $report['remaining_package_traffic_gb']),
        ];
    }

    private function formatCurrency(float $amount): string
    {
        return number_format($amount, 2).' USD';
    }

    private function formatGigabytes(float $gigabytes): string
    {
        return number_format($gigabytes, 2).' GB';
    }
}
