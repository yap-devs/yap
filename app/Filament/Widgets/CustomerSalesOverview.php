<?php

namespace App\Filament\Widgets;

use App\Services\CustomerSalesReportService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CustomerSalesOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'This Month';

    protected function getStats(): array
    {
        $month = last(app(CustomerSalesReportService::class)->monthly(1));

        return [
            Stat::make('New Customers', number_format($month['new_users']))
                ->description('Registered this month')
                ->color('info'),
            Stat::make('First-Time Buyers', number_format($month['first_time_buyers']))
                ->description('First paid top-up this month')
                ->color('success'),
            Stat::make('Returning Buyers', number_format($month['returning_buyers']))
                ->description('Paid this month after an earlier month')
                ->color('primary'),
            Stat::make('Paid Top-Ups', number_format($month['top_up'], 2).' USD')
                ->description('Successful payment orders')
                ->color('success'),
            Stat::make('Balance Charges', number_format($month['balance_charges'], 2).' USD')
                ->description('Balance debits, including manual adjustments')
                ->color('warning'),
        ];
    }
}
