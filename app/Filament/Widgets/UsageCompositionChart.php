<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasMobileFriendlyChart;
use App\Filament\Widgets\Concerns\InteractsWithDashboardControls;
use App\Services\AdminDashboardReportService;
use Filament\Widgets\ChartWidget;

class UsageCompositionChart extends ChartWidget
{
    use HasMobileFriendlyChart;
    use InteractsWithDashboardControls;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 4,
        'xl' => 4,
    ];

    protected ?string $heading = 'Balance Debit Mix';

    protected ?string $description = 'Charges for traffic billing, packages, and other product actions.';

    protected ?string $maxHeight = '320px';

    protected function getData(): array
    {
        $series = app(AdminDashboardReportService::class)->getUsageCompositionBreakdown($this->getTrendWindowMonths());

        return [
            'labels' => $series->keys()->all(),
            'datasets' => [
                [
                    'label' => 'Balance Debits (USD)',
                    'data' => $series->values()->all(),
                    'backgroundColor' => [
                        'rgba(244, 63, 94, 0.92)',
                        'rgba(249, 115, 22, 0.92)',
                        'rgba(250, 204, 21, 0.92)',
                        'rgba(99, 102, 241, 0.92)',
                        'rgba(107, 114, 128, 0.92)',
                    ],
                    'borderWidth' => 0,
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return $this->getMobileFriendlyOptions([
            'cutout' => '62%',
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
        ]);
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
