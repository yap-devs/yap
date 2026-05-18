<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardControls;
use App\Services\AdminDashboardReportService;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

class MonthlyTopUpAndUsageChart extends ChartWidget
{
    use InteractsWithDashboardControls;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = [
        'md' => 8,
        'xl' => 8,
    ];

    protected ?string $heading = 'Revenue vs Usage Forecast';

    protected ?string $description = 'Monthly cash-in, actual usage, current-month actual revenue, and projected month-end revenue.';

    protected ?string $maxHeight = '320px';

    protected function getData(): array
    {
        $report_service = app(AdminDashboardReportService::class);
        $top_up = $report_service->getMonthlyTopUpSeries($this->getTrendWindowMonths());
        $usage = $report_service->getMonthlyUsageSeries($this->getTrendWindowMonths());
        $projected_revenue = $report_service->getMonthlyRevenueProjectionSeries($this->getTrendWindowMonths());
        $current_month = CarbonImmutable::now()->format('Y-m');

        return [
            'labels' => $top_up->keys()->all(),
            'datasets' => [
                [
                    'label' => 'Monthly Revenue (USD)',
                    'data' => $top_up->values()->all(),
                    'backgroundColor' => 'rgba(34, 197, 94, 0.72)',
                    'borderColor' => 'rgba(34, 197, 94, 1)',
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                ],
                [
                    'label' => 'Usage (USD)',
                    'data' => $usage->values()->all(),
                    'backgroundColor' => 'rgba(244, 63, 94, 0.58)',
                    'borderColor' => 'rgba(244, 63, 94, 1)',
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                ],
                [
                    'type' => 'line',
                    'label' => 'Current Month Actual Revenue',
                    'data' => $top_up->map(fn (float $value, string $month): ?float => $month === $current_month ? $value : null)->values()->all(),
                    'borderColor' => 'rgba(14, 165, 233, 1)',
                    'backgroundColor' => 'rgba(14, 165, 233, 1)',
                    'pointBackgroundColor' => 'rgba(14, 165, 233, 1)',
                    'pointBorderColor' => 'rgba(255, 255, 255, 1)',
                    'pointBorderWidth' => 3,
                    'pointRadius' => 7,
                    'pointHoverRadius' => 9,
                    'borderWidth' => 0,
                    'spanGaps' => false,
                ],
                [
                    'type' => 'line',
                    'label' => 'Projected Month-End Revenue',
                    'data' => $projected_revenue->values()->all(),
                    'borderColor' => 'rgba(168, 85, 247, 1)',
                    'backgroundColor' => 'rgba(168, 85, 247, 0.18)',
                    'borderDash' => [6, 6],
                    'borderWidth' => 3,
                    'pointRadius' => 5,
                    'pointHoverRadius' => 7,
                    'tension' => 0.25,
                    'spanGaps' => false,
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                ],
            ],
            'interaction' => [
                'mode' => 'index',
                'intersect' => false,
            ],
            'scales' => [
                'x' => [
                    'stacked' => false,
                    'grid' => [
                        'display' => false,
                    ],
                ],
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
