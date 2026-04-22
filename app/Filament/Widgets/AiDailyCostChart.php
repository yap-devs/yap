<?php

namespace App\Filament\Widgets;

use App\Services\AdminDashboardReportService;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

class AiDailyCostChart extends ChartWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = '30s';

    protected int|string|array $columnSpan = [
        'md' => 6,
        'xl' => 6,
    ];

    protected ?string $heading = 'Daily AI Cost';

    protected ?string $description = 'Cost trend over the last 14 days.';

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $series = app(AdminDashboardReportService::class)->getAiDailyCostSeries(14);

        return [
            'labels' => $series->keys()->map(
                fn (string $day): string => CarbonImmutable::createFromFormat('Y-m-d', $day)->format('m/d'),
            )->all(),
            'datasets' => [
                [
                    'label' => 'Cost (USD)',
                    'data' => $series->values()->all(),
                    'borderColor' => 'rgba(244, 63, 94, 0.9)',
                    'backgroundColor' => 'rgba(244, 63, 94, 0.1)',
                    'fill' => true,
                    'tension' => 0.3,
                    'pointRadius' => 3,
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
        return 'line';
    }
}
