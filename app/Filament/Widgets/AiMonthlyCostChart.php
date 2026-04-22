<?php

namespace App\Filament\Widgets;

use App\Services\AdminDashboardReportService;
use Filament\Widgets\ChartWidget;

class AiMonthlyCostChart extends ChartWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = [
        'md' => 6,
        'xl' => 6,
    ];

    protected ?string $heading = 'Monthly AI Cost';

    protected ?string $description = 'Month-over-month AI spending trend.';

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $series = app(AdminDashboardReportService::class)->getAiMonthlyCostSeries(12);

        return [
            'labels' => $series->keys()->all(),
            'datasets' => [
                [
                    'label' => 'Cost (USD)',
                    'data' => $series->values()->all(),
                    'borderColor' => 'rgba(168, 85, 247, 0.9)',
                    'backgroundColor' => 'rgba(168, 85, 247, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                    'pointRadius' => 4,
                    'pointBackgroundColor' => 'rgba(168, 85, 247, 1)',
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
