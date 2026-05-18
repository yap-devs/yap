<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardControls;
use App\Services\AdminDashboardReportService;
use Filament\Widgets\ChartWidget;

class TodaySnapshotChart extends ChartWidget
{
    use InteractsWithDashboardControls;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = [
        'md' => 4,
        'xl' => 4,
    ];

    protected ?string $heading = 'Revenue Pace Snapshot';

    protected ?string $description = 'Money-only comparison for today, month-to-date revenue, forecast revenue, and usage.';

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $report = app(AdminDashboardReportService::class)->getOverviewStats($this->getTrendWindowMonths());
        $projected_revenue = app(AdminDashboardReportService::class)
            ->getMonthlyRevenueProjectionSeries($this->getTrendWindowMonths())
            ->filter()
            ->last() ?? 0;

        return [
            'labels' => ['Today Revenue', 'MTD Revenue', 'Projected Revenue', 'MTD Usage'],
            'datasets' => [
                [
                    'label' => 'USD',
                    'data' => [
                        $report['today_top_up'],
                        $report['current_month_top_up'],
                        $projected_revenue,
                        $report['current_month_usage'],
                    ],
                    'backgroundColor' => [
                        'rgba(14, 165, 233, 0.88)',
                        'rgba(34, 197, 94, 0.88)',
                        'rgba(168, 85, 247, 0.88)',
                        'rgba(244, 63, 94, 0.78)',
                    ],
                    'borderRadius' => 10,
                    'borderSkipped' => false,
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                ],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'grid' => [
                        'display' => false,
                    ],
                ],
                'y' => [
                    'grid' => [
                        'display' => false,
                    ],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
