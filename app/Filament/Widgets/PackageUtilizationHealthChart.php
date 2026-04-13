<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardControls;
use App\Services\AdminDashboardReportService;
use Filament\Widgets\ChartWidget;

class PackageUtilizationHealthChart extends ChartWidget
{
    use InteractsWithDashboardControls;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = [
        'md' => 4,
        'xl' => 4,
    ];

    protected ?string $heading = 'Package Utilization Health';

    protected ?string $description = 'Where active packages currently sit in their remaining traffic lifecycle.';

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $series = app(AdminDashboardReportService::class)->getPackageUtilizationBreakdown();

        return [
            'labels' => $series->keys()->all(),
            'datasets' => [
                [
                    'label' => 'Active packages',
                    'data' => $series->values()->all(),
                    'backgroundColor' => [
                        'rgba(244, 63, 94, 0.92)',
                        'rgba(251, 146, 60, 0.92)',
                        'rgba(96, 165, 250, 0.92)',
                        'rgba(34, 197, 94, 0.92)',
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
                    'display' => false,
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
