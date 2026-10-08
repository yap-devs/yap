<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\OperationsWorkspace;

test('operations workspace loads in the first viewport', function () {
    expect(app(Dashboard::class)->getWidgets())->toBe([OperationsWorkspace::class]);
    expect(OperationsWorkspace::isLazy())->toBeFalse();
});
