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

    protected ?string $heading = 'Today vs This Month';

    protected ?string $description = 'Side-by-side comparison of today\'s progress against the full month.';

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $report = app(AdminDashboardReportService::class)->getOverviewStats($this->getTrendWindowMonths());

        return [
            'labels' => ['Traffic (GB)', 'Top-Ups (USD)', 'Usage (USD)'],
            'datasets' => [
                [
                    'label' => 'Today',
                    'data' => [
                        $report['today_traffic_gb'],
                        $report['today_top_up'],
                        $report['today_usage'],
                    ],
                    'backgroundColor' => 'rgba(59, 130, 246, 0.85)',
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                ],
                [
                    'label' => 'This Month',
                    'data' => [
                        $report['current_month_traffic_gb'],
                        $report['current_month_top_up'],
                        $report['current_month_usage'],
                    ],
                    'backgroundColor' => 'rgba(148, 163, 184, 0.5)',
                    'borderRadius' => 8,
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
