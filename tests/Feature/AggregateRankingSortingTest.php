<?php

use App\Filament\Widgets\AiUsageRankingTable;
use App\Filament\Widgets\DailyTrafficRankingTable;
use App\Filament\Widgets\PaymentTopUpPeriodRankingTable;
use App\Filament\Widgets\PaymentTopUpRankingTable;
use App\Filament\Widgets\TwentyFourHourTrafficRankingTable;
use App\Models\User;
use Livewire\Livewire;

test('aggregate rankings never sort by ungrouped source primary keys', function (string $widget, string $source_table, ?string $sort_column) {
    $this->actingAs(User::factory()->create(['id' => 1]));
    $component = Livewire::test($widget);

    $orders = $component->instance()->getFilteredSortedTableQuery()->getQuery()->orders;

    expect(array_column($orders, 'column'))->not->toContain($source_table.'.id');

    if ($sort_column !== null) {
        $component->sortTable($sort_column, 'asc');
        $orders = $component->instance()->getFilteredSortedTableQuery()->getQuery()->orders;

        expect(array_column($orders, 'column'))->not->toContain($source_table.'.id')
            ->and($orders[0])->toBe(['column' => $sort_column, 'direction' => 'asc']);
    }
})->with([
    'period top ups' => [PaymentTopUpPeriodRankingTable::class, 'payments', 'total_top_up'],
    'lifetime top ups' => [PaymentTopUpRankingTable::class, 'payments', null],
    'ai usage' => [AiUsageRankingTable::class, 'sub2api_usage_records', 'total_cost'],
    'daily traffic' => [DailyTrafficRankingTable::class, 'user_stats', 'daily_traffic_bytes'],
    'daily traffic day' => [DailyTrafficRankingTable::class, 'user_stats', 'day'],
    'rolling traffic' => [TwentyFourHourTrafficRankingTable::class, 'user_stats', 'total_traffic_bytes'],
]);
