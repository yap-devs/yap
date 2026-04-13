<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardControls;
use App\Services\AdminDashboardReportService;
use Filament\Widgets\ChartWidget;

class UsageCompositionChart extends ChartWidget
{
    use InteractsWithDashboardControls;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = [
        'md' => 4,
        'xl' => 4,
    ];

    protected ?string $heading = 'Usage Composition';

    protected ?string $description = 'How actual user spending is split across billing and product actions.';

    protected ?string $maxHeight = '320px';

    protected function getData(): array
    {
        $series = app(AdminDashboardReportService::class)->getUsageCompositionBreakdown($this->getTrendWindowMonths());

        return [
            'labels' => $series->keys()->all(),
            'datasets' => [
                [
                    'label' => 'Usage (USD)',
                    'data' => $series->values()->all(),
                    'backgroundColor' => [
                        'rgba(244, 63, 94, 0.92)',
                        'rgba(249, 115, 22, 0.92)',
                        'rgba(250, 204, 21, 0.92)',
                        'rgba(99, 102, 241, 0.92)',
                    ],
                    'borderWidth' => 0,
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'cutout' => '62%',
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
