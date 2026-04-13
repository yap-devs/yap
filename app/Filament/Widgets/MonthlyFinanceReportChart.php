<?php

namespace App\Filament\Widgets;

use App\Services\AdminDashboardReportService;
use Filament\Widgets\ChartWidget;

class MonthlyFinanceReportChart extends ChartWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = [
        'md' => 5,
        'xl' => 5,
    ];

    protected ?string $pollingInterval = '15s';

    protected ?string $heading = 'Monthly Income / Cost Report';

    protected ?string $description = 'Revenue, cost, and net margin rendered in one finance view.';

    protected ?string $maxHeight = '320px';

    protected function getData(): array
    {
        $report_service = app(AdminDashboardReportService::class);
        $income = $report_service->getMonthlyIncomeSeries();
        $cost = $report_service->getMonthlyCostSeries();
        $net = $income->map(
            fn (float $value, string $month): float => round($value - (float) $cost->get($month, 0), 2),
        );

        return [
            'labels' => $income->keys()->all(),
            'datasets' => [
                [
                    'label' => 'Income (USD)',
                    'data' => $income->values()->all(),
                    'backgroundColor' => 'rgba(34, 197, 94, 0.7)',
                    'borderColor' => 'rgba(34, 197, 94, 1)',
                    'borderRadius' => 8,
                ],
                [
                    'label' => 'Cost (USD)',
                    'data' => $cost->values()->all(),
                    'backgroundColor' => 'rgba(244, 63, 94, 0.7)',
                    'borderColor' => 'rgba(244, 63, 94, 1)',
                    'borderRadius' => 8,
                ],
                [
                    'type' => 'line',
                    'label' => 'Net (USD)',
                    'data' => $net->values()->all(),
                    'borderColor' => 'rgba(245, 158, 11, 1)',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.2)',
                    'pointBackgroundColor' => 'rgba(245, 158, 11, 1)',
                    'tension' => 0.35,
                    'yAxisID' => 'y',
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
                'x' => [
                    'grid' => [
                        'display' => false,
                    ],
                ],
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
