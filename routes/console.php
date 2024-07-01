<?php

\Illuminate\Support\Facades\Schedule::command('app:update-stat-command')->everyOddHour();
\Illuminate\Support\Facades\Schedule::command('app:update-balance-command')->everySixHours();
