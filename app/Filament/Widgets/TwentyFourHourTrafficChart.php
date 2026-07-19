<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasMobileFriendlyChart;
use App\Filament\Widgets\Concerns\InteractsWithDashboardControls;
use App\Services\AdminDashboardReportService;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

class TwentyFourHourTrafficChart extends ChartWidget
{
    use HasMobileFriendlyChart;
    use InteractsWithDashboardControls;

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 12,
        'xl' => 12,
    ];

    protected ?string $heading = 'Hourly Traffic Trend';

    protected ?string $description = 'Downlink and uplink traffic across 24 consecutive one-hour intervals.';

    protected ?string $maxHeight = '360px';

    protected function getData(): array
    {
        $series = app(AdminDashboardReportService::class)->getLastTwentyFourHourTrafficSeries();

        return [
            'labels' => $series->keys()
                ->map(fn (string $hour): string => CarbonImmutable::createFromFormat('Y-m-d H:i', $hour)->format('m/d H:i'))
                ->all(),
            'datasets' => [
                [
                    'label' => 'Downlink (GB)',
                    'data' => $series->pluck('downlink_gb')->values()->all(),
                    'backgroundColor' => 'rgba(59, 130, 246, 0.12)',
                    'borderColor' => 'rgba(59, 130, 246, 1)',
                    'fill' => false,
                    'tension' => 0.3,
                    'pointRadius' => 3,
                    'pointHoverRadius' => 5,
                    'borderWidth' => 3,
                ],
                [
                    'label' => 'Uplink (GB)',
                    'data' => $series->pluck('uplink_gb')->values()->all(),
                    'backgroundColor' => 'rgba(16, 185, 129, 0.12)',
                    'borderColor' => 'rgba(16, 185, 129, 1)',
                    'fill' => false,
                    'tension' => 0.3,
                    'pointRadius' => 3,
                    'pointHoverRadius' => 5,
                    'borderWidth' => 3,
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return $this->getMobileFriendlyOptions([
            'interaction' => [
                'intersect' => false,
                'mode' => 'index',
            ],
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
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
        ]);
    }

    protected function getType(): string
    {
        return 'line';
    }
}
