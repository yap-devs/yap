<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardControls;
use App\Services\BackupStatusService;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BackupStatusWidget extends StatsOverviewWidget
{
    use InteractsWithDashboardControls;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Local Database Backups';

    public static function canView(): bool
    {
        return auth()->id() === 1;
    }

    protected function getStats(): array
    {
        $status = app(BackupStatusService::class)->overview();
        $latest = $status['latest'];
        $date = fn (?string $value): string => $value ? CarbonImmutable::parse($value)->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s T') : 'Not recorded yet';
        $labels = ['disabled' => 'Disabled', 'healthy' => 'Available', 'missing' => 'No backup yet', 'overdue' => 'Overdue', 'failed' => 'Last attempt failed', 'running' => 'Running', 'unavailable' => 'Unavailable'];
        $color = match ($status['status']) {
            'healthy' => 'success', 'disabled' => 'gray', 'running', 'missing' => 'warning', default => 'danger',
        };
        $last_time = $status['completed_at'] ?? ($latest ? CarbonImmutable::createFromTimestamp($latest['modified_at'])->toIso8601String() : null);

        return [
            Stat::make('Local Backup Status', $labels[$status['status']])
                ->description("Every {$status['interval_hours']} hour(s); retain {$status['retention_hours']} hours")
                ->color($color),
            Stat::make('Latest Backup', $date($last_time))
                ->description($status['completed_at'] ? 'Actual completion time' : 'File timestamp; completion not recorded'),
            Stat::make('Latest Backup Size', $latest ? $this->bytes($latest['bytes']) : 'No backup yet')
                ->description($status['gzip_verified_at'] ? 'Gzip checked at creation: '.$date($status['gzip_verified_at']) : 'Gzip verification not recorded; restore not verified'),
            Stat::make('Retained Backups', $status['count'])
                ->description('Total size: '.$this->bytes($status['total_bytes'])),
            Stat::make('Next Expected Backup', $status['enabled'] ? $date($status['next_expected_at']) : 'Disabled')
                ->description('Expected schedule; host delivery is not guaranteed'),
            Stat::make('Last Backup Attempt', $date($status['last_attempt']['finished_at'] ?? $status['last_attempt']['started_at'] ?? null))
                ->description('Status: '.($status['last_attempt']['status'] ?? 'not recorded'))
                ->color($status['status'] === 'failed' ? 'danger' : 'gray'),
        ];
    }

    private function bytes(int $bytes): string
    {
        $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
        $index = 0;
        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return number_format($bytes, $index === 0 ? 0 : 1).' '.$units[$index];
    }
}
