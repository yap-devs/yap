<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasMobileFriendlyChart;
use App\Filament\Widgets\Concerns\InteractsWithDashboardControls;
use App\Services\AdminDashboardReportService;
use Filament\Widgets\ChartWidget;

class MonthlyTopUpAndUsageChart extends ChartWidget
{
    use HasMobileFriendlyChart;
    use InteractsWithDashboardControls;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 8,
        'xl' => 8,
    ];

    protected ?string $heading = 'Monthly Top-Ups & Balance Debits';

    protected ?string $description = 'Paid funds received and charges deducted from user balances.';

    protected ?string $maxHeight = '320px';

    protected function getData(): array
    {
        $report_service = app(AdminDashboardReportService::class);
        $top_up = $report_service->getMonthlyTopUpSeries($this->getTrendWindowMonths());
        $usage = $report_service->getMonthlyUsageSeries($this->getTrendWindowMonths());

        return [
            'labels' => $top_up->keys()->all(),
            'datasets' => [
                [
                    'label' => 'Paid Top-Ups (USD)',
                    'data' => $top_up->values()->all(),
                    'backgroundColor' => 'rgba(34, 197, 94, 0.72)',
                    'borderColor' => 'rgba(34, 197, 94, 1)',
                    'grouped' => false,
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                ],
                [
                    'type' => 'line',
                    'label' => 'Balance Debits (USD)',
                    'data' => $usage->values()->all(),
                    'borderColor' => 'rgba(244, 63, 94, 1)',
                    'backgroundColor' => 'rgba(244, 63, 94, 0.14)',
                    'borderWidth' => 3,
                    'fill' => true,
                    'pointRadius' => 4,
                    'pointHoverRadius' => 6,
                    'tension' => 0.35,
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return $this->getMobileFriendlyOptions([
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                ],
            ],
            'interaction' => [
                'mode' => 'index',
                'intersect' => false,
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
        return 'bar';
    }
}
