<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\ReportOverviewWidget;

test('operations dashboard only loads overview widget in the first viewport', function () {
    $widgets = app(Dashboard::class)->getWidgets();

    expect($widgets)->toContain(ReportOverviewWidget::class)
        ->and(ReportOverviewWidget::isLazy())->toBeFalse();

    foreach (array_diff($widgets, [ReportOverviewWidget::class]) as $widget) {
        expect($widget::isLazy())->toBeTrue();
    }
});
