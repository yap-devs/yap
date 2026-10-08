<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\BackupStatusWidget;
use App\Filament\Widgets\ReportOverviewWidget;
use App\Filament\Widgets\SchedulerStatusWidget;

test('operations dashboard loads health and overview widgets in the first viewport', function () {
    $widgets = app(Dashboard::class)->getWidgets();

    expect($widgets)->toContain(ReportOverviewWidget::class)
        ->and(ReportOverviewWidget::isLazy())->toBeFalse();

    foreach (array_diff($widgets, [ReportOverviewWidget::class, SchedulerStatusWidget::class, BackupStatusWidget::class]) as $widget) {
        expect($widget::isLazy())->toBeTrue();
    }
});
