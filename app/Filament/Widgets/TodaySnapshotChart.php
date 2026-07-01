<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasMobileFriendlyChart;
use App\Filament\Widgets\Concerns\InteractsWithDashboardControls;
use App\Services\AdminDashboardReportService;
use Filament\Widgets\ChartWidget;

class TodaySnapshotChart extends ChartWidget
{
    use HasMobileFriendlyChart;
    use InteractsWithDashboardControls;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 4,
        'xl' => 4,
    ];

    protected ?string $heading = 'Top-Up Pace Snapshot';

    protected ?string $description = 'Money-only comparison for balance added, projected balance added, and consumed usage.';

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $report = app(AdminDashboardReportService::class)->getOverviewStats($this->getTrendWindowMonths());
        $projected_top_up = app(AdminDashboardReportService::class)
            ->getMonthlyTopUpProjectionSeries($this->getTrendWindowMonths())
            ->filter()
            ->last() ?? 0;

        return [
            'labels' => ['Today Top-Up', 'MTD Balance Added', 'Projected Balance Added', 'MTD Usage'],
            'datasets' => [
                [
                    'label' => 'USD',
                    'data' => [
                        $report['today_top_up'],
                        $report['current_month_top_up'],
                        $projected_top_up,
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
        return $this->getMobileFriendlyOptions([
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
        ]);
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
