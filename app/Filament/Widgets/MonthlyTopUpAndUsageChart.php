<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardControls;
use App\Services\AdminDashboardReportService;
use Filament\Widgets\ChartWidget;

class MonthlyTopUpAndUsageChart extends ChartWidget
{
    use InteractsWithDashboardControls;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = [
        'md' => 8,
        'xl' => 8,
    ];

    protected ?string $heading = 'Monthly Revenue & Usage';

    protected ?string $description = 'Actual revenue bars, current-month forecast bar, and actual usage line.';

    protected ?string $maxHeight = '320px';

    protected function getData(): array
    {
        $report_service = app(AdminDashboardReportService::class);
        $top_up = $report_service->getMonthlyTopUpSeries($this->getTrendWindowMonths());
        $usage = $report_service->getMonthlyUsageSeries($this->getTrendWindowMonths());
        $projected_revenue = $report_service->getMonthlyRevenueProjectionSeries($this->getTrendWindowMonths());

        return [
            'labels' => $top_up->keys()->all(),
            'datasets' => [
                [
                    'label' => 'Actual Revenue (USD)',
                    'data' => $top_up->values()->all(),
                    'backgroundColor' => 'rgba(34, 197, 94, 0.72)',
                    'borderColor' => 'rgba(34, 197, 94, 1)',
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                ],
                [
                    'label' => 'Projected Revenue (USD)',
                    'data' => $projected_revenue->values()->all(),
                    'backgroundColor' => 'rgba(168, 85, 247, 0.62)',
                    'borderColor' => 'rgba(168, 85, 247, 1)',
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                ],
                [
                    'type' => 'line',
                    'label' => 'Actual Usage (USD)',
                    'data' => $usage->values()->all(),
                    'borderColor' => 'rgba(244, 63, 94, 1)',
                    'backgroundColor' => 'rgba(244, 63, 94, 0.14)',
                    'borderWidth' => 3,
                    'fill' => true,
                    'pointRadius' => 4,
                    'pointHoverRadius' => 6,
                    'tension' => 0.35,
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
