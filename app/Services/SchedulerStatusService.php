<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

class SchedulerStatusService
{
    private function path(): string
    {
        return config('scheduler_status.path', storage_path('app/private/scheduler-status.json'));
    }

    public function read(): array
    {
        if (! is_file($this->path())) {
            return [];
        }
        try {
            return json_decode(file_get_contents($this->path()), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    public function record(string $key, array $values): void
    {
        File::ensureDirectoryExists(dirname($this->path()), 0700);
        $lock = fopen($this->path().'.lock', 'c');
        throw_unless($lock && flock($lock, LOCK_EX), RuntimeException::class, 'Cannot lock scheduler status.');
        try {
            $state = $this->read();
            $state[$key] = array_replace($state[$key] ?? [], $values);
            $temporary = $this->path().'.next';
            throw_if(file_put_contents($temporary, json_encode($state, JSON_THROW_ON_ERROR)) === false, RuntimeException::class, 'Cannot write scheduler status.');
            chmod($temporary, 0600);
            throw_unless(rename($temporary, $this->path()), RuntimeException::class, 'Cannot replace scheduler status.');
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function started(string $key): void
    {
        $this->recordSafely($key, ['started_at' => now()->toIso8601String(), 'status' => 'running', 'exit_code' => null]);
    }

    public function finished(string $key, int $exit_code): void
    {
        $this->recordSafely($key, ['finished_at' => now()->toIso8601String(), 'status' => $exit_code === 0 ? 'success' : 'failed', 'exit_code' => $exit_code]);
    }

    private function recordSafely(string $key, array $values): void
    {
        try {
            $this->record($key, $values);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function overview(): array
    {
        $state = $this->read();
        $last = isset($state['scheduler']['started_at']) ? CarbonImmutable::parse($state['scheduler']['started_at']) : null;
        $next = CarbonImmutable::now()->startOfMinute()->addMinute();
        $tasks = [];
        foreach (app(Schedule::class)->events() as $event) {
            $name = $event->description ?? $event->command ?? 'Scheduled task';
            $tasks[] = ['name' => $name, 'next_at' => $event->nextRunDate(now(), 0, false)->toIso8601String(), 'state' => $state[$name] ?? []];
        }

        return ['scheduler' => $state['scheduler'] ?? [], 'healthy' => $last !== null && $last->greaterThanOrEqualTo(now()->subMinutes(2)) && ($state['scheduler']['status'] ?? null) !== 'failed', 'next_tick' => $next->toIso8601String(), 'tasks' => $tasks];
    }
}
