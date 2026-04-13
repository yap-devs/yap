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

    protected ?string $heading = 'Monthly Top-Up / Usage Report';

    protected ?string $description = 'Cash-in versus money actually consumed, plotted across the selected trend window.';

    protected ?string $maxHeight = '320px';

    protected function getData(): array
    {
        $report_service = app(AdminDashboardReportService::class);
        $top_up = $report_service->getMonthlyTopUpSeries($this->getTrendWindowMonths());
        $usage = $report_service->getMonthlyUsageSeries($this->getTrendWindowMonths());

        return [
            'labels' => $top_up->keys()->all(),
            'datasets' => [
                [
                    'label' => 'Top-Ups (USD)',
                    'data' => $top_up->values()->all(),
                    'backgroundColor' => 'rgba(34, 197, 94, 0.7)',
                    'borderColor' => 'rgba(34, 197, 94, 1)',
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                ],
                [
                    'label' => 'Usage (USD)',
                    'data' => $usage->values()->all(),
                    'backgroundColor' => 'rgba(244, 63, 94, 0.7)',
                    'borderColor' => 'rgba(244, 63, 94, 1)',
                    'borderRadius' => 8,
                    'borderSkipped' => false,
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
