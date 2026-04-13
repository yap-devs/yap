<?php

namespace App\Filament\Widgets;

use App\Services\AdminDashboardReportService;
use Filament\Widgets\ChartWidget;

class MonthlyTrafficReportChart extends ChartWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = [
        'md' => 7,
        'xl' => 7,
    ];

    protected ?string $pollingInterval = '15s';

    protected ?string $heading = 'Monthly Traffic Report';

    protected ?string $description = '12-month usage trend aggregated from user traffic samples.';

    protected ?string $maxHeight = '320px';

    protected function getData(): array
    {
        $series = app(AdminDashboardReportService::class)->getMonthlyTrafficSeries();

        return [
            'labels' => $series->keys()->all(),
            'datasets' => [
                [
                    'label' => 'Traffic (GB)',
                    'data' => $series->values()->all(),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.12)',
                    'borderColor' => 'rgba(59, 130, 246, 1)',
                    'fill' => true,
                    'tension' => 0.35,
                    'pointRadius' => 4,
                    'pointHoverRadius' => 6,
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => false,
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
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
