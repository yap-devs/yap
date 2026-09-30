<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasMobileFriendlyChart;
use App\Services\CustomerSalesReportService;
use Filament\Widgets\ChartWidget;

class CustomerGrowthChart extends ChartWidget
{
    use HasMobileFriendlyChart;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Customer Growth and Top-Ups';

    protected ?string $description = 'Monthly registrations and unique buyers. Top-ups are successful payment amounts.';

    protected ?string $maxHeight = '320px';

    protected function getData(): array
    {
        $report = app(CustomerSalesReportService::class)->monthly(12);
        $months = collect($report);

        return [
            'labels' => array_keys($report),
            'datasets' => [
                [
                    'label' => 'New Customers',
                    'data' => $months->pluck('new_users')->all(),
                    'backgroundColor' => '#3b82f6',
                    'yAxisID' => 'customers',
                ],
                [
                    'label' => 'First-Time Buyers',
                    'data' => $months->pluck('first_time_buyers')->all(),
                    'backgroundColor' => '#10b981',
                    'yAxisID' => 'customers',
                ],
                [
                    'label' => 'Returning Buyers',
                    'data' => $months->pluck('returning_buyers')->all(),
                    'backgroundColor' => '#f59e0b',
                    'yAxisID' => 'customers',
                ],
                [
                    'label' => 'Paid Top-Ups (USD)',
                    'data' => $months->pluck('top_up')->all(),
                    'type' => 'line',
                    'borderColor' => '#e11d48',
                    'backgroundColor' => '#e11d48',
                    'yAxisID' => 'money',
                    'tension' => 0.3,
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return $this->getMobileFriendlyOptions([
            'scales' => [
                'customers' => ['type' => 'linear', 'position' => 'left', 'beginAtZero' => true],
                'money' => ['type' => 'linear', 'position' => 'right', 'beginAtZero' => true, 'grid' => ['drawOnChartArea' => false]],
            ],
        ]);
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
