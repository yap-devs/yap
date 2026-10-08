<?php

use App\Filament\Widgets\BackupStatusWidget;
use App\Models\User;
use App\Services\BackupStatusService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;

beforeEach(function () {
    $this->backup_directory = sys_get_temp_dir().'/yap-backup-status-'.bin2hex(random_bytes(8));
    mkdir($this->backup_directory, 0700);
    config([
        'backup.enabled' => true, 'backup.interval_hours' => 1, 'backup.retention_hours' => 168,
        'backup.path' => $this->backup_directory, 'backup.status_path' => $this->backup_directory.'/state.json',
        'database.connections.mysql.url' => null, 'database.connections.mysql.database' => 'status_test',
    ]);
    $this->travelTo(CarbonImmutable::parse('2026-10-07 12:05:00', config('app.timezone')));
    config(['database.connections.sqlite' => config('database.connections.mysql')]);
    $this->backup_file = $this->backup_directory.'/'.BackupStatusService::prefix('status_test').now()->utc()->format('YmdH').'.sql.gz';
    Process::fake();
});

afterEach(function () {
    $this->travelBack();
    File::deleteDirectory($this->backup_directory);
});

test('backup status distinguishes disabled missing and overdue without running processes', function () {
    $service = app(BackupStatusService::class);
    expect($service->overview()['status'])->toBe('missing');
    file_put_contents($this->backup_file, 'existing backup');
    touch($this->backup_file, now()->setTime(12, 0, 0)->timestamp);
    $status = $service->overview();
    expect($status['status'])->toBe('healthy')->and($status['gzip_verified_at'])->toBeNull();
    expect(CarbonImmutable::parse($status['next_expected_at'])->setTimezone('Asia/Tokyo')->format('H:i'))->toBe('13:00');
    $this->travelTo(now()->setTime(13, 10, 1));
    expect($service->overview()['status'])->toBe('overdue');
    config(['backup.enabled' => false]);
    expect($service->overview()['status'])->toBe('disabled')->and($service->overview()['count'])->toBe(1)
        ->and($service->overview()['next_expected_at'])->toBeNull();
    Process::assertNothingRan();
});

test('only final nonempty files for this database are counted', function () {
    file_put_contents($this->backup_file, 'valid backup');
    touch($this->backup_file, now()->timestamp);
    file_put_contents($this->backup_directory.'/'.BackupStatusService::prefix('other_database').'2026100703.sql.gz', 'unrelated');
    file_put_contents($this->backup_directory.'/.dump-abc.gz', 'temporary');
    file_put_contents($this->backup_directory.'/'.BackupStatusService::prefix('status_test').'2026100702.sql.gz', '');
    symlink($this->backup_file, $this->backup_directory.'/'.BackupStatusService::prefix('status_test').'2026100701.sql.gz');
    $status = app(BackupStatusService::class)->overview();
    expect($status['count'])->toBe(1)->and($status['total_bytes'])->toBe(strlen('valid backup'));
});

test('failed attempts preserve the last successful gzip evidence and skips do not overwrite it', function () {
    file_put_contents($this->backup_file, 'verified backup');
    touch($this->backup_file, now()->timestamp);
    $service = app(BackupStatusService::class);
    $service->started();
    $service->completed($this->backup_file);
    expect($service->overview()['status'])->toBe('healthy')->and($service->overview()['gzip_verified_at'])->not->toBeNull();
    $completed = $service->overview()['completed_at'];
    $this->travel(1)->minutes();
    $service->failed();
    expect($service->overview()['status'])->toBe('failed')->and($service->overview()['completed_at'])->toBe($completed)
        ->and(fileperms(config('backup.status_path')) & 0777)->toBe(0600);
    file_put_contents($this->backup_file, 'changed backup');
    expect($service->overview()['gzip_verified_at'])->toBeNull();
});

test('database and path changes do not inherit old verification or failures', function () {
    file_put_contents($this->backup_file, 'backup');
    $service = app(BackupStatusService::class);
    $service->completed($this->backup_file);
    $service->failed();
    config(['database.connections.sqlite.database' => 'other_database']);
    expect($service->overview()['status'])->toBe('missing')->and($service->overview()['last_attempt'])->toBe([]);
    config(['database.connections.sqlite.database' => 'status_test', 'backup.path' => $this->backup_directory.'/different']);
    expect($service->overview()['status'])->toBe('missing')->and($service->overview()['last_attempt'])->toBe([]);
});

test('unusable backup directories and configuration show unavailable rather than an empty inventory', function () {
    config(['backup.path' => $this->backup_directory.'/blocked']);
    file_put_contents(config('backup.path'), 'not a directory');
    expect(app(BackupStatusService::class)->overview()['status'])->toBe('unavailable');
    config(['backup.path' => $this->backup_directory, 'backup.interval_hours' => 0]);
    expect(app(BackupStatusService::class)->overview()['status'])->toBe('unavailable');
});

test('corrupt monitoring metadata does not hide available backup files', function () {
    file_put_contents($this->backup_file, 'existing backup');
    touch($this->backup_file, now()->timestamp);
    file_put_contents(config('backup.status_path'), '{invalid');
    expect(app(BackupStatusService::class)->overview()['status'])->toBe('healthy')
        ->and(app(BackupStatusService::class)->overview()['gzip_verified_at'])->toBeNull();
});

test('multi hour backup prediction uses utc buckets with japanese display times', function () {
    config(['backup.interval_hours' => 2]);
    $this->backup_file = $this->backup_directory.'/'.BackupStatusService::prefix('status_test').'2026100702.sql.gz';
    file_put_contents($this->backup_file, 'backup');
    touch($this->backup_file, now()->setTime(12, 0, 0)->timestamp);
    $next = app(BackupStatusService::class)->overview()['next_expected_at'];
    expect(CarbonImmutable::parse($next)->setTimezone('Asia/Tokyo')->format('H:i'))->toBe('13:00');
});

test('filament backup overview exposes no paths credentials or download actions', function () {
    $this->actingAs(User::factory()->create(['id' => 1]));
    file_put_contents($this->backup_file, 'backup');
    touch($this->backup_file, now()->timestamp);
    Livewire::test(BackupStatusWidget::class)->assertSee('Local Backup Status')->assertSee('Retained Backups')
        ->assertSee('Next Expected Backup')->assertSee('Gzip verification not recorded')
        ->assertDontSee($this->backup_directory)->assertDontSee('status_test');
    Process::assertNothingRan();
    $this->actingAs(User::factory()->create(['id' => 2]));
    expect(BackupStatusWidget::canView())->toBeFalse();
});

test('abandoned running attempts become overdue instead of staying running forever', function () {
    config(['backup.timeout_seconds' => 60]);
    $service = app(BackupStatusService::class);
    $service->started();
    expect($service->overview()['status'])->toBe('running');
    $this->travel(11)->minutes();
    expect($service->overview()['status'])->toBe('overdue');
});

test('missing long interval backups predict the next hourly retry rather than the next bucket', function () {
    config(['backup.interval_hours' => 6]);
    $next = app(BackupStatusService::class)->overview()['next_expected_at'];
    expect(CarbonImmutable::parse($next)->setTimezone('Asia/Tokyo')->format('H:i'))->toBe('13:00');
});

test('a backup completed across a bucket boundary does not conceal a missed cycle', function () {
    file_put_contents($this->backup_file, 'backup');
    $this->travelTo(now()->setTime(13, 11, 0));
    touch($this->backup_file, now()->timestamp);
    $status = app(BackupStatusService::class)->overview();
    expect($status['status'])->toBe('overdue');
    expect(CarbonImmutable::parse($status['next_expected_at'])->setTimezone('Asia/Tokyo')->format('H:i'))->toBe('14:00');
});

test('valid json with malformed running timestamps falls back to unknown metadata', function () {
    $service = app(BackupStatusService::class);
    $service->started();
    $state = json_decode(file_get_contents(config('backup.status_path')), true);
    $state['last_attempt']['started_at'] = 'not a timestamp';
    file_put_contents(config('backup.status_path'), json_encode($state));
    expect($service->overview()['status'])->toBe('missing');
});

test('metadata stat errors are nonfatal and do not replace the last successful backup', function () {
    file_put_contents($this->backup_file, 'backup');
    $service = app(BackupStatusService::class);
    $service->completed($this->backup_file);
    $completed = $service->overview()['completed_at'];
    $service->completed($this->backup_directory.'/does-not-exist.sql.gz');
    expect($service->overview()['completed_at'])->toBe($completed);
});

test('overdue backups do not become healthy during the grace window of later missed cycles', function () {
    file_put_contents($this->backup_file, 'backup');
    touch($this->backup_file, now()->timestamp);
    $this->travelTo(now()->setTime(14, 5, 0));
    expect(app(BackupStatusService::class)->overview()['status'])->toBe('overdue');
});

test('fractional timezone backup health follows the actual local hourly schedule', function (string $timezone, string $completed_utc, string $next_utc) {
    config(['app.timezone' => $timezone]);
    $this->travelTo(CarbonImmutable::parse('2026-10-07 04:11:00', 'UTC'));
    file_put_contents($this->backup_file, 'backup');
    touch($this->backup_file, CarbonImmutable::parse($completed_utc, 'UTC')->timestamp);
    $service = app(BackupStatusService::class);
    $status = $service->overview();
    expect($status['status'])->toBe('healthy')
        ->and(CarbonImmutable::parse($status['next_expected_at'])->utc()->format('Y-m-d H:i'))->toBe($next_utc);
    $this->travelTo(CarbonImmutable::parse($next_utc, 'UTC')->addMinutes(10)->addSecond());
    expect($service->overview()['status'])->toBe('overdue');
    $this->travel(1)->hours();
    expect($service->overview()['status'])->toBe('overdue');
})->with([
    ['Asia/Kolkata', '2026-10-07 03:30:00', '2026-10-07 04:30'],
    ['Asia/Kathmandu', '2026-10-07 03:15:00', '2026-10-07 04:15'],
    ['Australia/Adelaide', '2026-10-07 03:30:00', '2026-10-07 04:30'],
    ['America/St_Johns', '2026-10-07 03:30:00', '2026-10-07 04:30'],
]);

test('existing multi hour backup buckets predict the first eligible local cron tick', function () {
    config(['app.timezone' => 'Asia/Kolkata', 'backup.interval_hours' => 2]);
    $this->backup_file = $this->backup_directory.'/'.BackupStatusService::prefix('status_test').'2026100702.sql.gz';
    file_put_contents($this->backup_file, 'backup');
    $this->travelTo(CarbonImmutable::parse('2026-10-07 03:11:00', 'UTC'));
    touch($this->backup_file, CarbonImmutable::parse('2026-10-07 02:30:00', 'UTC')->timestamp);
    $status = app(BackupStatusService::class)->overview();
    expect($status['status'])->toBe('healthy')
        ->and(CarbonImmutable::parse($status['next_expected_at'])->utc()->format('Y-m-d H:i'))->toBe('2026-10-07 04:30');
});

test('backup prediction preserves hourly execution across daylight saving transitions', function (string $current, string $bucket, string $expected) {
    config(['app.timezone' => 'America/New_York']);
    $this->travelTo(CarbonImmutable::parse($current, 'UTC'));
    $this->backup_file = $this->backup_directory.'/'.BackupStatusService::prefix('status_test').$bucket.'.sql.gz';
    file_put_contents($this->backup_file, 'backup');
    touch($this->backup_file, now()->subMinutes(5)->timestamp);
    expect(CarbonImmutable::parse(app(BackupStatusService::class)->overview()['next_expected_at'])->utc()->format('Y-m-d H:i'))->toBe($expected);
})->with([
    ['2026-03-08 06:05:00', '2026030806', '2026-03-08 07:00'],
    ['2026-11-01 05:05:00', '2026110105', '2026-11-01 06:00'],
]);

test('manual dashboard refresh reloads backup status', function () {
    $this->actingAs(User::factory()->create(['id' => 1]));
    config(['backup.enabled' => false]);
    $widget = Livewire::test(BackupStatusWidget::class)->assertSee('Disabled');
    config(['backup.enabled' => true]);
    $widget->dispatch('dashboard-refresh')->assertSee('No backup yet')->assertDontSee('Disabled');
});
