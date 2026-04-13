<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardControls;
use App\Services\AdminDashboardReportService;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

class LastSevenDayUsageChart extends ChartWidget
{
    use InteractsWithDashboardControls;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = [
        'md' => 4,
        'xl' => 4,
    ];

    protected ?string $heading = 'Last 7-Day Usage Report';

    protected ?string $description = 'Daily actual spend deducted from user balances over the latest rolling week.';

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $series = app(AdminDashboardReportService::class)->getLastSevenDayUsageSeries();

        return [
            'labels' => $series->keys()->map(
                fn (string $day): string => CarbonImmutable::createFromFormat('Y-m-d', $day)->format('m/d'),
            )->all(),
            'datasets' => [
                [
                    'label' => 'Usage (USD)',
                    'data' => $series->values()->all(),
                    'backgroundColor' => [
                        'rgba(251, 113, 133, 0.95)',
                        'rgba(251, 146, 60, 0.95)',
                        'rgba(250, 204, 21, 0.95)',
                        'rgba(163, 230, 53, 0.95)',
                        'rgba(45, 212, 191, 0.95)',
                        'rgba(96, 165, 250, 0.95)',
                        'rgba(167, 139, 250, 0.95)',
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
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
