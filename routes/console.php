<?php

\Illuminate\Support\Facades\Schedule::command('app:update-stat-command')->everyFifteenMinutes();
\Illuminate\Support\Facades\Schedule::command('app:process-payment-command')->everyMinute();
\Illuminate\Support\Facades\Schedule::command('app:package-status-notification-command')->weeklyOn(1, '02:00'); // Every Monday at 2 AM
