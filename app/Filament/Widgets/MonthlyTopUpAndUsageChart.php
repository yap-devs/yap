<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasMobileFriendlyChart;
use App\Filament\Widgets\Concerns\InteractsWithDashboardControls;
use App\Services\AdminDashboardReportService;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

class MonthlyTopUpAndUsageChart extends ChartWidget
{
    use HasMobileFriendlyChart;
    use InteractsWithDashboardControls;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'md' => 8,
        'xl' => 8,
    ];

    protected ?string $heading = 'Monthly Top-Up & Usage';

    protected ?string $description = 'Balance added, current-month top-up forecast extension, and actual consumed usage.';

    protected ?string $maxHeight = '320px';

    protected function getData(): array
    {
        $report_service = app(AdminDashboardReportService::class);
        $top_up = $report_service->getMonthlyTopUpSeries($this->getTrendWindowMonths());
        $usage = $report_service->getMonthlyUsageSeries($this->getTrendWindowMonths());
        $projected_top_up = $report_service->getMonthlyTopUpProjectionSeries($this->getTrendWindowMonths());
        $current_month = CarbonImmutable::now()->format('Y-m');
        $forecast_range = $projected_top_up->map(
            fn (?float $projected, string $month): ?array => $month === $current_month && (float) $projected > (float) $top_up->get($month, 0)
                ? [(float) $top_up->get($month, 0), (float) $projected]
                : null,
        );

        return [
            'labels' => $top_up->keys()->all(),
            'datasets' => [
                [
                    'label' => 'Balance Added (USD)',
                    'data' => $top_up->values()->all(),
                    'backgroundColor' => 'rgba(34, 197, 94, 0.72)',
                    'borderColor' => 'rgba(34, 197, 94, 1)',
                    'grouped' => false,
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                ],
                [
                    'label' => 'Month-End Top-Up Forecast (USD)',
                    'data' => $forecast_range->values()->all(),
                    'backgroundColor' => 'rgba(168, 85, 247, 0.32)',
                    'borderColor' => 'rgba(168, 85, 247, 1)',
                    'borderWidth' => 2,
                    'borderDash' => [6, 4],
                    'grouped' => false,
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                ],
                [
                    'type' => 'line',
                    'label' => 'Consumed Usage (USD)',
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
