<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardControls;
use App\Services\AdminDashboardReportService;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

class LastSevenDayTrafficChart extends ChartWidget
{
    use InteractsWithDashboardControls;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = [
        'md' => 4,
        'xl' => 4,
    ];

    protected ?string $heading = 'Last 7-Day Traffic';

    protected ?string $description = 'Daily bandwidth consumption over the latest rolling week.';

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $series = app(AdminDashboardReportService::class)->getLastSevenDayTrafficSeries();

        return [
            'labels' => $series->keys()->map(
                fn (string $day): string => CarbonImmutable::createFromFormat('Y-m-d', $day)->format('m/d'),
            )->all(),
            'datasets' => [
                [
                    'label' => 'Traffic (GB)',
                    'data' => $series->values()->all(),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.15)',
                    'borderColor' => 'rgba(59, 130, 246, 1)',
                    'fill' => true,
                    'tension' => 0.35,
                    'pointRadius' => 4,
                    'pointHoverRadius' => 6,
                    'borderWidth' => 3,
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
        return 'line';
    }
}
