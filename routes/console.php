<?php


//\Illuminate\Support\Facades\Schedule::command('app:gen-sub-link-command')->hourly();
\Illuminate\Support\Facades\Schedule::command('app:update-stat-command')->everyFourHours();
\Illuminate\Support\Facades\Schedule::command('app:update-balance-command')->daily();
