<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasMobileFriendlyChart;
use App\Filament\Widgets\Concerns\InteractsWithDashboardControls;
use App\Services\AdminDashboardReportService;
use Filament\Widgets\ChartWidget;

class MonthlyTrafficReportChart extends ChartWidget
{
    use HasMobileFriendlyChart;
    use InteractsWithDashboardControls;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 8,
        'xl' => 8,
    ];

    protected ?string $heading = 'Monthly Traffic Report';

    protected ?string $description = 'Bandwidth demand curve across the selected reporting window.';

    protected ?string $maxHeight = '320px';

    protected function getData(): array
    {
        $series = app(AdminDashboardReportService::class)->getMonthlyTrafficSeries($this->getTrendWindowMonths());

        return [
            'labels' => $series->keys()->all(),
            'datasets' => [
                [
                    'label' => 'Traffic (GB)',
                    'data' => $series->values()->all(),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.12)',
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
        return $this->getMobileFriendlyOptions([
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
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
        ]);
    }

    protected function getType(): string
    {
        return 'line';
    }
}
