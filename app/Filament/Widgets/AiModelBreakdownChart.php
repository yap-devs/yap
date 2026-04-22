<?php

namespace App\Filament\Widgets;

use App\Services\AdminDashboardReportService;
use Filament\Widgets\ChartWidget;

class AiModelBreakdownChart extends ChartWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = [
        'md' => 6,
        'xl' => 6,
    ];

    protected ?string $heading = 'Model Cost Breakdown';

    protected ?string $description = 'AI spending distribution by model.';

    protected ?string $maxHeight = '280px';

    protected function getData(): array
    {
        $models = app(AdminDashboardReportService::class)->getAiModelBreakdown(12);

        $palette = [
            'rgba(244, 63, 94, 0.88)',
            'rgba(249, 115, 22, 0.88)',
            'rgba(234, 179, 8, 0.88)',
            'rgba(34, 197, 94, 0.88)',
            'rgba(6, 182, 212, 0.88)',
            'rgba(99, 102, 241, 0.88)',
            'rgba(168, 85, 247, 0.88)',
            'rgba(236, 72, 153, 0.88)',
        ];

        return [
            'labels' => $models->pluck('model')->all(),
            'datasets' => [
                [
                    'label' => 'Cost (USD)',
                    'data' => $models->pluck('cost')->all(),
                    'backgroundColor' => array_slice(
                        array_merge($palette, $palette),
                        0,
                        $models->count()
                    ),
                    'borderWidth' => 0,
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'cutout' => '55%',
            'plugins' => [
                'legend' => [
                    'position' => 'right',
                    'labels' => [
                        'boxWidth' => 12,
                        'padding' => 12,
                    ],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
