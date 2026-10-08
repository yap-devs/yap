<?php

use App\Services\SchedulerStatusService;
use Illuminate\Console\Scheduling\Schedule as Scheduler;
use Illuminate\Support\Facades\Schedule;

$timezone = config('app.timezone');

Schedule::command('app:process-payment-command')
    ->everyMinute()
    ->timezone($timezone)
    ->withoutOverlapping(10);

Schedule::command('app:update-stat-command')
    ->cron('2-59/10 * * * *')
    ->timezone($timezone)
    ->withoutOverlapping(30);

Schedule::command('app:sync-sub2api-command')
    ->cron('7,37 * * * *')
    ->timezone($timezone)
    ->withoutOverlapping(45);

Schedule::command('affiliate:credit-pending-commissions')
    ->hourlyAt(17)
    ->timezone($timezone)
    ->withoutOverlapping(90);

Schedule::command('app:sync-client-downloads')
    ->dailyAt('00:23')
    ->timezone($timezone)
    ->withoutOverlapping(120);

Schedule::command('affiliate:expire-referrals')
    ->dailyAt('00:47')
    ->timezone($timezone)
    ->withoutOverlapping(30);

Schedule::command('app:package-status-notification-command')
    ->dailyAt('01:05')
    ->timezone($timezone)
    ->withoutOverlapping(60);

Schedule::command('app:package-low-traffic-notification-command')
    ->weeklyOn(1, '01:25')
    ->timezone($timezone)
    ->withoutOverlapping(120);

Schedule::command('nodes:aggregate-traffic')
    ->hourly()
    ->timezone($timezone)
    ->withoutOverlapping(10);

Schedule::command('app:backup-mysql')
    ->hourly()
    ->timezone($timezone)
    ->withoutOverlapping(60);

Schedule::command('nodes:prune-traffic')
    ->hourlyAt(40)
    ->timezone($timezone)
    ->withoutOverlapping(10);

foreach (app(Scheduler::class)->events() as $event) {
    preg_match('/artisan[\'"]?\s+(\S+)/', $event->command, $matches);
    $name = $matches[1] ?? $event->command;
    $event->name($name)
        ->before(fn () => app(SchedulerStatusService::class)->started($name))
        ->after(fn () => app(SchedulerStatusService::class)->finished($name, $event->exitCode ?? 1));
}
