<?php

use App\Jobs\GenerateClashProfileLink;
use App\Models\User;
use App\Models\VmessServer;
use App\Services\SubscriptionService;
use App\Services\V2rayService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Support\Facades\Cache;

test('a failed node does not stop later nodes and the job throws a summary', function () {
    $first_service = Mockery::mock(V2rayService::class);
    $first_service->shouldReceive('addOrRemoveUsers')->once()->andThrow(new RuntimeException('node failure'));

    $second_service = Mockery::mock(V2rayService::class);
    $second_service->shouldReceive('addOrRemoveUsers')->once();

    $services = [
        'first.example.com' => $first_service,
        'second.example.com' => $second_service,
    ];

    app()->bind(V2rayService::class, function ($app, array $parameters) use ($services) {
        return $services[$parameters['internal_server']];
    });

    $first_user = User::factory()->make([
        'uuid' => 'first-user-id',
        'email' => 'first@example.com',
    ]);
    $second_user = User::factory()->make([
        'uuid' => 'second-user-id',
        'email' => 'second@example.com',
    ]);
    $first_server = new VmessServer(['internal_server' => 'first.example.com']);
    $second_server = new VmessServer(['internal_server' => 'second.example.com']);
    $job = new GenerateClashProfileLink;
    $method = new ReflectionMethod($job, 'processV2Ray');

    expect(fn () => $method->invoke($job, [
        [$first_user, [$first_server]],
        [$second_user, [$second_server]],
    ]))->toThrow(RuntimeException::class, 'Failed to update 1 V2ray server(s)');
});

test('the generation job is unique while queued and has retry settings', function () {
    $job = new GenerateClashProfileLink;

    expect($job)->toBeInstanceOf(ShouldBeUniqueUntilProcessing::class)
        ->and($job->tries)->toBe(3)
        ->and($job->timeout)->toBe(300)
        ->and($job->uniqueFor)->toBeGreaterThan($job->timeout)
        ->and($job->backoff())->toBe([60, 300])
        ->and(config('queue.connections.database.retry_after'))->toBeGreaterThan($job->timeout)
        ->and(config('queue.connections.redis.retry_after'))->toBeGreaterThan($job->timeout)
        ->and(config('queue.connections.beanstalkd.retry_after'))->toBeGreaterThan($job->timeout);
});

test('direct job execution is protected by the processing lock', function () {
    $lock = Cache::lock('generate-clash-profile-link:processing', 330);
    expect($lock->get())->toBeTrue();

    try {
        $subscription_service = Mockery::mock(SubscriptionService::class);
        $subscription_service->shouldNotReceive('serversFor');

        expect(fn () => (new GenerateClashProfileLink)->handle($subscription_service))
            ->toThrow(LockTimeoutException::class);
    } finally {
        $lock->release();
    }
});
