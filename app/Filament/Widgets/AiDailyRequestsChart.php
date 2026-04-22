<?php

namespace App\Filament\Widgets;

use App\Services\AdminDashboardReportService;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

class AiDailyRequestsChart extends ChartWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '30s';

    protected int|string|array $columnSpan = [
        'md' => 6,
        'xl' => 6,
    ];

    protected ?string $heading = 'Daily AI Requests';

    protected ?string $description = 'Request volume over the last 14 days.';

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $series = app(AdminDashboardReportService::class)->getAiDailyRequestSeries(14);

        return [
            'labels' => $series->keys()->map(
                fn (string $day): string => CarbonImmutable::createFromFormat('Y-m-d', $day)->format('m/d'),
            )->all(),
            'datasets' => [
                [
                    'label' => 'Requests',
                    'data' => $series->values()->all(),
                    'backgroundColor' => [
                        'rgba(96, 165, 250, 0.85)',
                        'rgba(129, 140, 248, 0.85)',
                        'rgba(167, 139, 250, 0.85)',
                        'rgba(192, 132, 252, 0.85)',
                        'rgba(232, 121, 249, 0.85)',
                        'rgba(244, 114, 182, 0.85)',
                        'rgba(251, 113, 133, 0.85)',
                        'rgba(251, 146, 60, 0.85)',
                        'rgba(250, 204, 21, 0.85)',
                        'rgba(163, 230, 53, 0.85)',
                        'rgba(52, 211, 153, 0.85)',
                        'rgba(45, 212, 191, 0.85)',
                        'rgba(34, 211, 238, 0.85)',
                        'rgba(56, 189, 248, 0.85)',
                    ],
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'x' => ['grid' => ['display' => false]],
                'y' => ['beginAtZero' => true],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
