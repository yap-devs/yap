<?php

namespace App\Services;

use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

class LocalMysqlBackupService
{
    public function run(): bool
    {
        if (! config('backup.enabled')) {
            return false;
        }

        try {
            return $this->createIfDue();
        } catch (Throwable $exception) {
            app(BackupStatusService::class)->failed();

            throw $exception;
        }
    }

    private function createIfDue(): bool
    {
        $database = (new ConfigurationUrlParser)->parseConfiguration(config('database.connections.'.config('database.default')));
        throw_if(! in_array($database['driver'] ?? null, ['mysql', 'mariadb'], true) || empty($database['database']), RuntimeException::class, 'Invalid MySQL backup connection.');
        $directory = rtrim((string) config('backup.path'), '/');
        throw_if(! str_starts_with($directory, DIRECTORY_SEPARATOR) || (int) config('backup.retention_hours') < 1 || (int) config('backup.interval_hours') < 1, RuntimeException::class, 'Invalid backup configuration.');
        throw_if(! is_dir($directory) && ! mkdir($directory, 0700, true), RuntimeException::class, 'Cannot create backup directory.');
        $directory = $this->validateDirectory($directory);
        $lock = fopen($directory.'/.backup.lock', 'c');
        throw_if($lock === false, RuntimeException::class, 'Cannot open backup lock.');

        try {
            if (! flock($lock, LOCK_EX | LOCK_NB)) {
                return false;
            }

            $prefix = BackupStatusService::prefix((string) $database['database']);
            $interval = (int) config('backup.interval_hours') * 3600;
            $period = now()->utc()->setTimestamp(intdiv(now()->timestamp, $interval) * $interval);
            $destination = $directory.'/'.$prefix.$period->format('YmdH').'.sql.gz';
            $existing = glob($directory.'/'.$prefix.'??????????.sql.gz') ?: [];
            if (is_file($destination)) {
                return false;
            }

            app(BackupStatusService::class)->started();
            $this->create($directory, $destination, $database);
            app(BackupStatusService::class)->completed($destination);
            $cutoff = now()->timestamp - (int) config('backup.retention_hours') * 3600;
            foreach ($existing as $file) {
                if (! is_link($file) && filemtime($file) < $cutoff) {
                    unlink($file);
                }
            }

            return true;
        } finally {
            fclose($lock);
        }
    }

    private function validateDirectory(string $directory): string
    {
        clearstatcache();
        $resolved = realpath($directory);
        $public = realpath(public_path());
        throw_if(is_link($directory) || $resolved === false || (fileperms($resolved) & 0077) !== 0, RuntimeException::class, 'Backup directory must be private and cannot be a symlink.');
        throw_if($public !== false && ($resolved === $public || str_starts_with($resolved, $public.DIRECTORY_SEPARATOR)), RuntimeException::class, 'Backup directory cannot be publicly accessible.');
        $parent = dirname($resolved);
        while (true) {
            $permissions = fileperms($parent);
            throw_if(($permissions & 0022) !== 0 && ($permissions & 01000) === 0, RuntimeException::class, 'Backup directory has an unsafe writable parent.');
            if ($parent === dirname($parent)) {
                break;
            }
            $parent = dirname($parent);
        }

        return $resolved;
    }

    /** @param array<string, mixed> $database */
    private function create(string $directory, string $destination, array $database): void
    {
        $credentials = tempnam($directory, '.credentials-');
        $dump = tempnam($directory, '.dump-');
        try {
            throw_if($credentials === false || $dump === false, RuntimeException::class, 'Cannot create backup temporary files.');
            throw_if(dirname($credentials) !== $directory || dirname($dump) !== $directory || fileowner($credentials) !== fileowner($directory) || fileowner($dump) !== fileowner($directory), RuntimeException::class, 'Backup directory must belong to the executing user.');
            throw_if(! chmod($credentials, 0600) || ! chmod($dump, 0600), RuntimeException::class, 'Cannot secure backup temporary files.');
            $options = [
                'user' => $database['username'] ?? '',
                'password' => $database['password'] ?? '',
                'host' => $database['host'] ?? '127.0.0.1',
                'port' => $database['port'] ?? 3306,
            ];
            if (! empty($database['unix_socket'])) {
                $options['socket'] = $database['unix_socket'];
            }
            if (defined('PDO::MYSQL_ATTR_SSL_CA') && ! empty($database['options'][\PDO::MYSQL_ATTR_SSL_CA])) {
                $options['ssl-ca'] = $database['options'][\PDO::MYSQL_ATTR_SSL_CA];
            }
            $contents = "[client]\n";
            foreach ($options as $key => $value) {
                $contents .= $key.'="'.strtr((string) $value, ['\\' => '\\\\', '"' => '\\"', "\n" => '\\n', "\r" => '\\r', "\t" => '\\t', "\x08" => '\\b']).'"'."\n";
            }
            throw_if(file_put_contents($credentials, $contents) !== strlen($contents), RuntimeException::class, 'Cannot write backup credentials.');
            $this->execute([
                (string) config('backup.mysqldump_binary'),
                '--defaults-extra-file='.$credentials,
                '--single-transaction', '--quick', '--no-tablespaces', '--hex-blob',
                '--result-file='.$dump,
                '--databases', '--', (string) $database['database'],
            ]);
            clearstatcache(true, $dump);
            throw_if(filesize($dump) === 0, RuntimeException::class, 'Database dump is empty.');
            $this->execute([(string) config('backup.gzip_binary'), '-f', '--', $dump]);
            $this->execute([(string) config('backup.gzip_binary'), '-t', '--', $dump.'.gz']);
            throw_if(! is_file($dump.'.gz') || ! chmod($dump.'.gz', 0600) || ! rename($dump.'.gz', $destination), RuntimeException::class, 'Cannot publish backup.');
        } finally {
            foreach ([$credentials, $dump, $dump === false ? false : $dump.'.gz'] as $file) {
                if ($file !== false && is_file($file)) {
                    unlink($file);
                }
            }
        }
    }

    /** @param array<int, string> $command */
    private function execute(array $command): void
    {
        $result = Process::timeout(max(1, (int) config('backup.timeout_seconds')))->quietly()->run($command);
        throw_if(! $result->successful(), RuntimeException::class, 'Backup process failed.');
    }
}
