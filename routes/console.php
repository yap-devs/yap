<?php

\Illuminate\Support\Facades\Schedule::command('app:update-stat-command')->everyThirtyMinutes();
\Illuminate\Support\Facades\Schedule::command('app:process-payment-command')->everyMinute();
\Illuminate\Support\Facades\Schedule::command('app:package-status-notification-command')->daily();
