<?php

use App\Services\BackupStatusService;
use App\Services\LocalMysqlBackupService;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->backup_directory = sys_get_temp_dir().'/yap-backup-'.bin2hex(random_bytes(8));
    mkdir($this->backup_directory, 0700);
    config([
        'database.default' => 'mysql',
        'backup.status_path' => $this->backup_directory.'/status.json',
        'backup.enabled' => true,
        'backup.path' => $this->backup_directory,
        'backup.interval_hours' => 1,
        'backup.retention_hours' => 168,
        'database.connections.mysql.url' => null,
        'database.connections.mysql.database' => 'backup_test',
        'database.connections.mysql.username' => 'backup_user',
        'database.connections.mysql.password' => "secret\"\\\n#value",
    ]);
    Process::preventStrayProcesses();
    $this->prefix = 'mysql-'.substr(hash('sha256', 'backup_test'), 0, 12).'-';
    $this->old_backup = $this->backup_directory.'/'.$this->prefix.'2020010100.sql.gz';
    file_put_contents($this->old_backup, 'old backup');
    touch($this->old_backup, time() - 169 * 3600);
});

afterEach(function () {
    $this->travelBack();
    File::deleteDirectory($this->backup_directory);
});

function fakeLocalBackupProcesses(?string $failure = null): void
{
    Process::fake(function (PendingProcess $process) use ($failure) {
        $command = $process->command;
        if ($command[0] === 'mysqldump') {
            $credentials = substr($command[1], strlen('--defaults-extra-file='));
            expect(fileperms($credentials) & 0777)->toBe(0600)
                ->and(file_get_contents($credentials))->toContain('password="secret\\"\\\\\\n#value"')
                ->and(implode(' ', $command))->not->toContain('backup_user', 'secret')
                ->and($command)->toContain('--single-transaction', '--quick', '--no-tablespaces', '--hex-blob');
            $dump = substr($command[6], strlen('--result-file='));
            file_put_contents($dump, 'CREATE TABLE example (id bigint);');
        } elseif ($command[1] === '-f') {
            file_put_contents($command[3].'.gz', gzencode(file_get_contents($command[3])));
            touch($command[3].'.gz', now()->timestamp);
            unlink($command[3]);
        }

        return Process::result(exitCode: ($failure === 'dump' && $command[0] === 'mysqldump') || ($failure === 'verify' && $command[1] === '-t') ? 1 : 0);
    });
}

test('creates a private verified backup and prunes expired backups after success', function () {
    fakeLocalBackupProcesses();

    expect(app(LocalMysqlBackupService::class)->run())->toBeTrue();
    $files = glob($this->backup_directory.'/*.sql.gz');
    expect($files)->toHaveCount(1)
        ->and(gzdecode(file_get_contents($files[0])))->toBe('CREATE TABLE example (id bigint);')
        ->and(fileperms($files[0]) & 0777)->toBe(0600)
        ->and(file_exists($this->old_backup))->toBeFalse()
        ->and(glob($this->backup_directory.'/.credentials-*'))->toBeEmpty()
        ->and(glob($this->backup_directory.'/.dump-*'))->toBeEmpty();
    expect(app(BackupStatusService::class)->overview()['gzip_verified_at'])->not->toBeNull();
    Process::assertRanTimes(fn (): bool => true, 3);
});

test('retains old backups and cleans temporary files on process failure', function (string $failure) {
    fakeLocalBackupProcesses($failure);

    $this->artisan('app:backup-mysql')->assertFailed();

    expect(app(BackupStatusService::class)->overview()['status'])->toBe('failed');
    expect(file_exists($this->old_backup))->toBeTrue()
        ->and(glob($this->backup_directory.'/*.sql.gz'))->toHaveCount(1)
        ->and(glob($this->backup_directory.'/.credentials-*'))->toBeEmpty()
        ->and(glob($this->backup_directory.'/.dump-*'))->toBeEmpty();
})->with(['dump', 'verify']);

test('skips disabled backups without invoking a process', function () {
    Process::fake();
    config(['backup.enabled' => false]);

    $this->artisan('app:backup-mysql')->assertSuccessful();

    Process::assertNothingRan();
    expect(file_exists($this->old_backup))->toBeTrue();
});

test('avoids duplicate backups and respects the configured interval', function () {
    fakeLocalBackupProcesses();
    expect(app(LocalMysqlBackupService::class)->run())->toBeTrue()
        ->and(app(LocalMysqlBackupService::class)->run())->toBeFalse();
    Process::assertRanTimes(fn (): bool => true, 3);
});

test('skips when another backup owns the local lock', function () {
    Process::fake();
    $lock = fopen($this->backup_directory.'/.backup.lock', 'c');
    flock($lock, LOCK_EX);
    try {
        expect(app(LocalMysqlBackupService::class)->run())->toBeFalse();
        Process::assertNothingRan();
    } finally {
        fclose($lock);
    }
});

test('runs again in the next configured interval', function () {
    fakeLocalBackupProcesses();
    config(['backup.interval_hours' => 2]);
    $this->travelTo(now()->utc()->startOfDay());

    expect(app(LocalMysqlBackupService::class)->run())->toBeTrue();
    $this->travel(1)->hours();
    expect(app(LocalMysqlBackupService::class)->run())->toBeFalse();
    $this->travel(1)->hours();
    expect(app(LocalMysqlBackupService::class)->run())->toBeTrue();
    Process::assertRanTimes(fn (): bool => true, 6);
});

test('uses resolved database url credentials without exposing them in arguments', function () {
    config(['database.connections.mysql.url' => 'mysql://url_user:url_password@db.example:3307/url_database']);
    Process::fake(function (PendingProcess $process) {
        $command = $process->command;
        $credentials = substr($command[1], strlen('--defaults-extra-file='));
        expect(file_get_contents($credentials))->toContain('user="url_user"', 'password="url_password"', 'host="db.example"', 'port="3307"')
            ->and($command)->toContain('url_database')
            ->and(implode(' ', $command))->not->toContain('url_password', 'url_user');

        return Process::result(exitCode: 1);
    });

    $this->artisan('app:backup-mysql')->assertFailed();
    expect(glob($this->backup_directory.'/.credentials-*'))->toBeEmpty();
});

test('monitoring preserves verified backups on failure and recovers on the next successful attempt', function () {
    fakeLocalBackupProcesses();
    $backup = app(LocalMysqlBackupService::class);
    $status = app(BackupStatusService::class);
    expect($backup->run())->toBeTrue();
    $completed = $status->overview()['completed_at'];
    $this->travel(1)->hours();
    fakeLocalBackupProcesses('dump');
    $this->artisan('app:backup-mysql')->assertFailed();
    expect($status->overview()['status'])->toBe('failed')->and($status->overview()['completed_at'])->toBe($completed);
    fakeLocalBackupProcesses();
    expect($backup->run())->toBeTrue()->and($status->overview()['status'])->toBe('healthy')
        ->and($status->overview()['completed_at'])->not->toBe($completed);
});

test('configuration stage failures are monitored before a dump can start', function () {
    config(['backup.path' => $this->backup_directory.'/blocked']);
    file_put_contents(config('backup.path'), 'not a directory');
    Process::fake();
    $this->artisan('app:backup-mysql')->assertFailed();
    $state = json_decode(file_get_contents(config('backup.status_path')), true);
    expect($state['last_attempt']['status'])->toBe('failed');
    Process::assertNothingRan();
});

test('unwritable monitoring storage cannot invalidate a successful backup', function () {
    file_put_contents($this->backup_directory.'/blocked', 'not a directory');
    config(['backup.status_path' => $this->backup_directory.'/blocked/status.json']);
    fakeLocalBackupProcesses();
    expect(app(LocalMysqlBackupService::class)->run())->toBeTrue();
    $files = glob($this->backup_directory.'/*.sql.gz');
    expect($files)->toHaveCount(1)->and(gzdecode(file_get_contents($files[0])))->toBe('CREATE TABLE example (id bigint);');
});

test('rejects shared backup directories before starting a process', function (int $mode) {
    fakeLocalBackupProcesses();
    chmod($this->backup_directory, $mode);
    expect(fn () => app(LocalMysqlBackupService::class)->run())->toThrow(RuntimeException::class);
    Process::assertNothingRan();
})->with([0777, 0770, 0755]);

test('rejects backups inside the public document root', function () {
    fakeLocalBackupProcesses();
    app()->usePublicPath($this->backup_directory);
    expect(fn () => app(LocalMysqlBackupService::class)->run())->toThrow(RuntimeException::class);
    Process::assertNothingRan();
});

test('rejects a symlink backup directory', function () {
    fakeLocalBackupProcesses();
    $link = $this->backup_directory.'/linked';
    symlink($this->backup_directory, $link);
    config(['backup.path' => $link]);
    try {
        expect(fn () => app(LocalMysqlBackupService::class)->run())->toThrow(RuntimeException::class);
        Process::assertNothingRan();
    } finally {
        unlink($link);
    }
});

test('creates a missing private backup directory', function () {
    fakeLocalBackupProcesses();
    $directory = $this->backup_directory.'/new';
    config(['backup.path' => $directory]);
    expect(app(LocalMysqlBackupService::class)->run())->toBeTrue()
        ->and(fileperms($directory) & 0777)->toBe(0700);
});

test('rejects private backups under a shared writable parent', function () {
    fakeLocalBackupProcesses();
    $directory = $this->backup_directory.'/private';
    mkdir($directory, 0700);
    chmod($this->backup_directory, 0777);
    config(['backup.path' => $directory]);
    expect(fn () => app(LocalMysqlBackupService::class)->run())->toThrow(RuntimeException::class);
    Process::assertNothingRan();
});

test('rejects a nested public backup directory', function () {
    fakeLocalBackupProcesses();
    app()->usePublicPath($this->backup_directory);
    config(['backup.path' => $this->backup_directory.'/nested']);
    expect(fn () => app(LocalMysqlBackupService::class)->run())->toThrow(RuntimeException::class);
    Process::assertNothingRan();
});

test('backs up the configured mariadb connection with or without a url', function (bool $use_url) {
    $connection = config('database.connections.mysql');
    $connection['driver'] = 'mariadb';
    $connection['database'] = 'mariadb_backup';
    $connection['host'] = 'db.example';
    $connection['port'] = 3307;
    $connection['url'] = $use_url ? 'mariadb://backup_user:secret%22%5C%0A%23value@db.example:3307/mariadb_backup' : null;
    config(['database.default' => 'mariadb', 'database.connections.mariadb' => $connection]);
    fakeLocalBackupProcesses();
    expect(app(LocalMysqlBackupService::class)->run())->toBeTrue();
    Process::assertRan(fn (PendingProcess $process): bool => $process->command[0] === 'mysqldump' && in_array('mariadb_backup', $process->command, true));
    expect(app(BackupStatusService::class)->overview()['status'])->toBe('healthy')
        ->and(app(BackupStatusService::class)->overview()['gzip_verified_at'])->not->toBeNull();
})->with([false, true]);
