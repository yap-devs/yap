<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardControls;
use App\Services\AdminDashboardReportService;
use Filament\Widgets\ChartWidget;

class UserAccessHealthChart extends ChartWidget
{
    use InteractsWithDashboardControls;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = [
        'md' => 4,
        'xl' => 4,
    ];

    protected ?string $heading = 'User Access Health';

    protected ?string $description = 'A snapshot of how safe the current user base is from balance exhaustion.';

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $series = app(AdminDashboardReportService::class)->getAccessHealthBreakdown();

        return [
            'labels' => $series->keys()->all(),
            'datasets' => [
                [
                    'label' => 'Users',
                    'data' => $series->values()->all(),
                    'backgroundColor' => [
                        'rgba(34, 197, 94, 0.92)',
                        'rgba(59, 130, 246, 0.92)',
                        'rgba(250, 204, 21, 0.92)',
                        'rgba(251, 146, 60, 0.92)',
                        'rgba(244, 63, 94, 0.92)',
                    ],
                    'borderWidth' => 0,
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
            'scales' => [
                'r' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'polarArea';
    }
}
