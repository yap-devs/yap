<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config(['queue.connections.database.connection' => 'sqlite', 'queue.failed.database' => 'sqlite']);
    $this->travelTo(now()->startOfSecond());
});

test('queue health distinguishes ready delayed reserved and expired jobs without exposing payloads', function () {
    $now = now()->timestamp;
    foreach ([
        ['default', null, $now - 90, $now - 100],
        ['default', null, $now + 60, $now - 200],
        ['default', $now - 20, $now - 40, $now - 80],
        ['default', $now - 400, $now - 500, $now - 600],
        ['other', null, $now - 10, $now - 20],
    ] as [$queue, $reserved_at, $available_at, $created_at]) {
        DB::table('jobs')->insert(compact('queue', 'reserved_at', 'available_at', 'created_at') + ['payload' => 'private job data', 'attempts' => 0]);
    }
    DB::table('failed_jobs')->insert(['uuid' => 'test-failure', 'connection' => 'database', 'queue' => 'default', 'payload' => 'private job data', 'exception' => 'private exception', 'failed_at' => now()]);

    expect(Artisan::call('app:queue-health'))->toBe(0);
    $output = Artisan::output();
    $health = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
    expect($health['queues']['default'])->toBe([
        'total' => 4, 'ready' => 1, 'delayed' => 1, 'reserved' => 1, 'expired' => 1,
        'oldest_created_age_seconds' => 600, 'oldest_ready_age_seconds' => 90,
    ])->and($health['queues']['other']['ready'])->toBe(1)
        ->and($health['failed_jobs'])->toBe(1)
        ->and($health['worker_queue'])->toBe('default')
        ->and($output)->not->toContain('private job data', 'private exception');
});

test('queue health writes an atomic snapshot for an empty configured queue', function () {
    config(['queue.connections.database.queue' => 'notifications']);
    $path = sys_get_temp_dir().'/yap-health-'.bin2hex(random_bytes(8)).'.json';
    try {
        expect(Artisan::call('app:queue-health', ['--output' => $path]))->toBe(0);
        $health = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        expect($health['worker_queue'])->toBe('notifications')
            ->and($health['queues'])->toBe([])->and($health['failed_jobs'])->toBe(0)
            ->and($health['observed_at'])->toBe(now()->timestamp);
    } finally {
        @unlink($path);
    }
});

test('queue preflight rejects reservation windows too short for the watchdog', function (int $retry_after) {
    config(['queue.connections.database.retry_after' => $retry_after]);
    $this->artisan('app:queue-health')->expectsOutputToContain('retry_after must exceed 60 seconds')->assertFailed();
})->with([0, 55, 60]);
