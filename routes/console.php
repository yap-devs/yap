<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('app:update-stat-command')->everyTenMinutes()->withoutOverlapping();
Schedule::command('app:process-payment-command')->everyMinute()->withoutOverlapping();
Schedule::command('app:sync-sub2api-command')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('app:package-status-notification-command')->weeklyOn(1, '02:00'); // Every Monday at 2 AM
