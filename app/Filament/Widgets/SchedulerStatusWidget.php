<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardControls;
use App\Services\SchedulerStatusService;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SchedulerStatusWidget extends StatsOverviewWidget
{
    use InteractsWithDashboardControls;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Scheduler Health';

    protected function getStats(): array
    {
        $status = app(SchedulerStatusService::class)->overview();
        $run = $status['scheduler'];
        $format = fn (?string $date): string => $date ? CarbonImmutable::parse($date)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s T') : 'Not recorded yet';
        $failed = collect($status['tasks'])->filter(fn (array $task): bool => ($task['state']['status'] ?? null) === 'failed');
        $next_task = collect($status['tasks'])->sortBy('next_at')->first();

        return [
            Stat::make('Last Scheduler Run', $format($run['started_at'] ?? null))
                ->description($status['healthy'] ? 'Recent tick received' : 'No recent tick or scheduler failed')
                ->color($status['healthy'] ? 'success' : 'danger'),
            Stat::make('Last Scheduler Completion', $format($run['finished_at'] ?? null))
                ->description('Status: '.($run['status'] ?? 'unknown')),
            Stat::make('Next Expected Cron Tick', $format($status['next_tick']))
                ->description('Expected every minute; host delivery is not guaranteed'),
            Stat::make('Next Scheduled Task', $format($next_task['next_at'] ?? null))
                ->description($next_task['name'] ?? 'No scheduled tasks'),
            Stat::make('Scheduled Task Failures', $failed->count())
                ->description($failed->isEmpty() ? 'No recorded task failures' : $failed->pluck('name')->implode(', '))
                ->color($failed->isEmpty() ? 'success' : 'danger'),
        ];
    }
}
