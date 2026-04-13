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
        'md' => 5,
        'xl' => 5,
    ];

    protected ?string $heading = 'Monthly Top-Up / Usage Report';

    protected ?string $description = 'Top-ups received versus actual balance deductions consumed by users.';

    protected ?string $maxHeight = '320px';

    protected function getData(): array
    {
        $report_service = app(AdminDashboardReportService::class);
        $top_up = $report_service->getMonthlyTopUpSeries();
        $usage = $report_service->getMonthlyUsageSeries();

        return [
            'labels' => $top_up->keys()->all(),
            'datasets' => [
                [
                    'label' => 'Top-Ups (USD)',
                    'data' => $top_up->values()->all(),
                    'backgroundColor' => 'rgba(34, 197, 94, 0.7)',
                    'borderColor' => 'rgba(34, 197, 94, 1)',
                    'borderRadius' => 8,
                ],
                [
                    'label' => 'Usage (USD)',
                    'data' => $usage->values()->all(),
                    'backgroundColor' => 'rgba(244, 63, 94, 0.7)',
                    'borderColor' => 'rgba(244, 63, 94, 1)',
                    'borderRadius' => 8,
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
            ],
            'scales' => [
                'x' => [
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
