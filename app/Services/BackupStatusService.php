<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Cron\CronExpression;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

class BackupStatusService
{
    public static function prefix(string $database): string
    {
        return 'mysql-'.substr(hash('sha256', $database), 0, 12).'-';
    }

    private function identity(): string
    {
        $database = (new ConfigurationUrlParser)->parseConfiguration(config('database.connections.'.config('database.default')));

        return hash('sha256', serialize([$database['host'] ?? '', $database['port'] ?? '', $database['unix_socket'] ?? '', $database['database'] ?? '', rtrim((string) config('backup.path'), '/')]));
    }

    private function read(): array
    {
        $path = config('backup.status_path');
        if (! is_file($path)) {
            return [];
        }
        try {
            $state = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($state) || ($state['identity'] ?? null) !== $this->identity()) {
                return [];
            }
            foreach (['last_attempt', 'last_success'] as $key) {
                if (! isset($state[$key])) {
                    continue;
                }
                throw_unless(is_array($state[$key]), RuntimeException::class, 'Invalid backup status metadata.');
                foreach (['started_at', 'finished_at', 'completed_at', 'gzip_verified_at'] as $field) {
                    if (isset($state[$key][$field])) {
                        throw_unless(is_string($state[$key][$field]), RuntimeException::class, 'Invalid backup status time.');
                        CarbonImmutable::parse($state[$key][$field]);
                    }
                }
            }
            throw_if(($state['last_attempt']['status'] ?? null) === 'running' && empty($state['last_attempt']['started_at']), RuntimeException::class, 'Missing backup start time.');

            return $state;
        } catch (Throwable $exception) {
            report($exception);

            return [];
        }
    }

    private function record(array $values): void
    {
        $lock = null;
        try {
            $path = config('backup.status_path');
            File::ensureDirectoryExists(dirname($path), 0700);
            $lock = fopen($path.'.lock', 'c');
            throw_unless($lock && flock($lock, LOCK_EX), RuntimeException::class, 'Cannot lock backup status.');
            chmod($path.'.lock', 0600);
            $contents = json_encode(array_replace($this->read(), ['identity' => $this->identity()], $values), JSON_THROW_ON_ERROR);
            throw_unless(file_put_contents($path.'.next', $contents) === strlen($contents) && chmod($path.'.next', 0600) && rename($path.'.next', $path), RuntimeException::class, 'Cannot publish backup status.');
        } catch (Throwable $exception) {
            report($exception);
        } finally {
            if (is_resource($lock)) {
                fclose($lock);
            }
        }
    }

    public function started(): void
    {
        $this->record(['last_attempt' => ['status' => 'running', 'started_at' => now()->toIso8601String()]]);
    }

    public function failed(): void
    {
        $this->record(['last_attempt' => ['status' => 'failed', 'finished_at' => now()->toIso8601String()]]);
    }

    public function completed(string $file): void
    {
        try {
            clearstatcache(true, $file);
            $this->record([
                'last_attempt' => ['status' => 'success', 'finished_at' => now()->toIso8601String()],
                'last_success' => ['file' => basename($file), 'bytes' => filesize($file), 'modified_at' => filemtime($file), 'completed_at' => now()->toIso8601String(), 'gzip_verified_at' => now()->toIso8601String()],
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function overview(): array
    {
        $enabled = (bool) config('backup.enabled');
        $directory = rtrim((string) config('backup.path'), '/');
        $interval_hours = (int) config('backup.interval_hours');
        $retention_hours = (int) config('backup.retention_hours');
        $interval = max(1, $interval_hours) * 3600;
        $database = (new ConfigurationUrlParser)->parseConfiguration(config('database.connections.'.config('database.default')));
        $prefix = self::prefix((string) ($database['database'] ?? ''));
        $state = $this->read();
        $files = [];
        $unavailable = $directory === '' || (file_exists($directory) && (! is_dir($directory) || ! is_readable($directory)));
        if (! $unavailable && is_dir($directory)) {
            foreach (glob($directory.'/'.$prefix.'??????????.sql.gz') ?: [] as $file) {
                if (is_link($file) || ! is_file($file) || ! preg_match('/^'.preg_quote($prefix, '/').'\d{10}\.sql\.gz$/', basename($file))) {
                    continue;
                }
                clearstatcache(true, $file);
                try {
                    $bytes = filesize($file);
                    $modified = filemtime($file);
                } catch (Throwable) {
                    continue;
                }
                if ($bytes !== false && $bytes > 0 && $modified !== false) {
                    $date = substr(basename($file), strlen($prefix), 10);
                    try {
                        $period = CarbonImmutable::createFromFormat('!YmdH', $date, 'UTC');
                    } catch (Throwable) {
                        continue;
                    }
                    if ($period->format('YmdH') !== $date) {
                        continue;
                    }
                    $files[] = ['name' => basename($file), 'bytes' => $bytes, 'modified_at' => $modified, 'period_at' => $period->timestamp];
                }
            }
        }
        usort($files, fn (array $a, array $b): int => ($b['modified_at'] <=> $a['modified_at']) ?: strcmp($b['name'], $a['name']));
        $latest = $files[0] ?? null;
        $success = $state['last_success'] ?? [];
        $verified = $latest !== null && ($success['file'] ?? null) === $latest['name'] && ($success['bytes'] ?? null) === $latest['bytes'] && ($success['modified_at'] ?? null) === $latest['modified_at'];
        $running = ($state['last_attempt']['status'] ?? null) === 'running';
        $stalled = $running && CarbonImmutable::parse($state['last_attempt']['started_at'])->timestamp < now()->timestamp - max(600, (int) config('backup.timeout_seconds') * 3);
        $current_period = intdiv(now()->timestamp, $interval) * $interval;
        $current_name = $prefix.CarbonImmutable::createFromTimestamp($current_period)->utc()->format('YmdH').'.sql.gz';
        $current_exists = is_file($directory.'/'.$current_name) && ! is_link($directory.'/'.$current_name);
        $hourly = new CronExpression('0 * * * *');
        $timezone = config('app.timezone');
        $next_timestamp = $hourly->getNextRunDate(
            $current_exists ? CarbonImmutable::createFromTimestamp($current_period + $interval) : now(),
            0,
            $current_exists,
            $timezone,
        )->getTimestamp();
        $latest_period = $latest === null ? null : intdiv($latest['period_at'], $interval) * $interval;
        $next_after_latest = $latest_period === null ? null : $hourly->getNextRunDate(
            CarbonImmutable::createFromTimestamp($latest_period + $interval),
            0,
            true,
            $timezone,
        )->getTimestamp();
        $overdue = $next_after_latest !== null && $latest_period < $current_period && now()->timestamp > $next_after_latest + 600;
        $status = match (true) {
            ! $enabled => 'disabled',
            $unavailable || $interval_hours < 1 || $retention_hours < 1 => 'unavailable',
            ($state['last_attempt']['status'] ?? null) === 'failed' => 'failed',
            $stalled => 'overdue',
            $running => 'running',
            $latest === null => 'missing',
            $overdue => 'overdue',
            default => 'healthy',
        };

        return [
            'enabled' => $enabled, 'status' => $status, 'interval_hours' => $interval_hours, 'retention_hours' => $retention_hours,
            'latest' => $latest, 'count' => count($files), 'total_bytes' => array_sum(array_column($files, 'bytes')),
            'gzip_verified_at' => $verified ? ($success['gzip_verified_at'] ?? null) : null,
            'completed_at' => $verified ? ($success['completed_at'] ?? null) : null,
            'last_attempt' => $state['last_attempt'] ?? [],
            'next_expected_at' => $enabled ? CarbonImmutable::createFromTimestamp($next_timestamp)->toIso8601String() : null,
        ];
    }
}
