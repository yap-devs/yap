<?php

use App\Filament\Pages\TwentyFourHourTraffic;
use App\Filament\Widgets\TwentyFourHourTrafficChart;
use App\Filament\Widgets\TwentyFourHourTrafficOverviewWidget;
use App\Filament\Widgets\TwentyFourHourTrafficRankingTable;
use App\Models\User;
use Filament\Tables\Table;
use Livewire\Livewire;

test('it configures the 24 hour traffic page and widgets', function () {
    $page = app(TwentyFourHourTraffic::class);
    $overview_widget = app(TwentyFourHourTrafficOverviewWidget::class);
    $chart_widget = app(TwentyFourHourTrafficChart::class);
    $ranking_widget = app(TwentyFourHourTrafficRankingTable::class);
    $chart_data = invokeTwentyFourHourTrafficProtectedMethod($chart_widget, 'getData');
    $chart_options = invokeTwentyFourHourTrafficProtectedMethod($chart_widget, 'getOptions');
    $ranking_table = $ranking_widget->table(Table::make($ranking_widget));

    expect($page->getColumns())->toBe([
        'md' => 12,
        'xl' => 12,
    ])->and($page->getWidgets())->toBe([
        TwentyFourHourTrafficOverviewWidget::class,
        TwentyFourHourTrafficChart::class,
        TwentyFourHourTrafficRankingTable::class,
    ])->and(TwentyFourHourTrafficOverviewWidget::isLazy())->toBeFalse()
        ->and($overview_widget->getColumnSpan())->toBe('full')
        ->and($chart_widget->getColumnSpan())->toBe([
            'default' => 'full',
            'md' => 12,
            'xl' => 12,
        ])
        ->and($ranking_widget->getColumnSpan())->toBe('full')
        ->and($chart_options['responsive'])->toBeTrue()
        ->and($chart_options['maintainAspectRatio'])->toBeFalse()
        ->and($chart_data['labels'])->toHaveCount(24)
        ->and($chart_data['datasets'])->toHaveCount(2)
        ->and($chart_data['datasets'][0]['label'])->toBe('Downlink (GB)')
        ->and($chart_data['datasets'][1]['label'])->toBe('Uplink (GB)')
        ->and($ranking_table->isStackedOnMobile())->toBeTrue()
        ->and(getTwentyFourHourTrafficProtectedProperty($overview_widget, 'pollingInterval'))->toBe('60s')
        ->and(getTwentyFourHourTrafficProtectedProperty($chart_widget, 'pollingInterval'))->toBe('60s')
        ->and(getTwentyFourHourTrafficProtectedProperty($ranking_widget, 'pollingInterval'))->toBe('60s');
});

test('admin can render and refresh the 24 hour traffic page', function () {
    $this->actingAs(User::factory()->create(['id' => 1]));

    Livewire::test(TwentyFourHourTraffic::class)
        ->assertOk()
        ->call('refreshTraffic')
        ->assertDispatched('dashboard-refresh');
});

function invokeTwentyFourHourTrafficProtectedMethod(object $object, string $method): mixed
{
    $reflection = new ReflectionMethod($object, $method);

    return $reflection->invoke($object);
}

function getTwentyFourHourTrafficProtectedProperty(object $object, string $property): mixed
{
    $reflection = new ReflectionProperty($object, $property);

    return $reflection->getValue($object);
}
