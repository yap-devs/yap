<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardControls;
use App\Services\AdminDashboardReportService;
use Filament\Widgets\ChartWidget;

class GatewayTopUpShareChart extends ChartWidget
{
    use InteractsWithDashboardControls;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = [
        'md' => 4,
        'xl' => 4,
    ];

    protected ?string $heading = 'Top-Up Channel Mix';

    protected ?string $description = 'Which payment channels are bringing money into the platform.';

    protected ?string $maxHeight = '320px';

    protected function getData(): array
    {
        $series = app(AdminDashboardReportService::class)->getGatewayTopUpBreakdown($this->getTrendWindowMonths());

        return [
            'labels' => $series->keys()->all(),
            'datasets' => [
                [
                    'label' => 'Top-Ups (USD)',
                    'data' => $series->values()->all(),
                    'backgroundColor' => [
                        'rgba(59, 130, 246, 0.92)',
                        'rgba(34, 197, 94, 0.92)',
                        'rgba(168, 85, 247, 0.92)',
                        'rgba(251, 146, 60, 0.92)',
                        'rgba(148, 163, 184, 0.92)',
                    ],
                    'borderWidth' => 0,
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'cutout' => '68%',
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
