<?php

use App\Filament\Widgets\SchedulerStatusWidget;
use App\Models\User;
use App\Services\SchedulerStatusService;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

beforeEach(function () {
    $this->status_directory = sys_get_temp_dir().'/yap-scheduler-test-'.bin2hex(random_bytes(8));
    config(['scheduler_status.path' => $this->status_directory.'/status.json']);
});

afterEach(function () {
    File::deleteDirectory($this->status_directory);
});

test('scheduler command events record actual ticks and completion without running business tasks', function () {
    $input = new ArrayInput([]);
    $output = new NullOutput;
    Event::dispatch(new CommandStarting('schedule:run', $input, $output));
    $service = app(SchedulerStatusService::class);
    expect($service->overview()['healthy'])->toBeTrue()
        ->and($service->read()['scheduler']['status'])->toBe('running');
    Event::dispatch(new CommandFinished('schedule:run', $input, $output, 1));
    expect($service->overview()['healthy'])->toBeFalse()
        ->and($service->read()['scheduler']['status'])->toBe('failed');
});

test('missing and stale cron ticks show unhealthy while next dates follow schedule timezones', function () {
    $this->travelTo(now()->setTime(12, 4, 30));
    $service = app(SchedulerStatusService::class);
    expect($service->overview()['healthy'])->toBeFalse();
    $service->started('scheduler');
    $service->finished('scheduler', 0);
    $this->travel(3)->minutes();
    expect($service->overview()['healthy'])->toBeFalse();
    $next = collect($service->overview()['tasks'])->first();
    expect($next['next_at'])->toBe(now()->startOfMinute()->addMinute()->toIso8601String());
});

test('task status stays bounded and successful completion clears its previous failure', function () {
    $service = app(SchedulerStatusService::class);
    for ($i = 0; $i < 20; $i++) {
        $service->started('payment');
        $service->finished('payment', 1);
    }
    expect($service->read())->toHaveCount(1);
    $service->started('payment');
    $service->finished('payment', 0);
    expect($service->read()['payment']['status'])->toBe('success')
        ->and(fileperms(config('scheduler_status.path')) & 0777)->toBe(0600);
});

test('filament displays scheduler health without executing scheduled business tasks', function () {
    $this->actingAs(User::factory()->create(['id' => 1]));
    Livewire::test(SchedulerStatusWidget::class)->assertSee('Last Scheduler Run')
        ->assertSee('Next Expected Cron Tick')->assertSee('Not recorded yet');
});

test('status storage failure cannot stop business scheduling', function () {
    mkdir($this->status_directory, 0700, true);
    file_put_contents($this->status_directory.'/blocked', 'not a directory');
    config(['scheduler_status.path' => $this->status_directory.'/blocked/status.json']);
    $service = app(SchedulerStatusService::class);
    $service->started('scheduler');
    $service->finished('scheduler', 0);
    expect($service->overview()['healthy'])->toBeFalse();
});
