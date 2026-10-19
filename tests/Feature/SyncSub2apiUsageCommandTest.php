<?php

use App\Models\User;
use App\Services\Sub2apiKeyService;
use App\Services\Sub2apiService;
use Illuminate\Support\Facades\Exceptions;

test('sub2api sync reports failures and continues across user chunks', function () {
    Exceptions::fake();
    config()->set('logging.channels.job', ['driver' => 'null']);
    $users = User::factory()->count(101)->sequence(fn ($sequence) => [
        'sub2api_key_id' => $sequence->index + 1,
        'sub2api_key_status' => Sub2apiKeyService::STATUS_ACTIVE,
        'balance' => 5,
    ])->create();
    $exception = new RuntimeException('Upstream unavailable');
    $service = Mockery::mock(Sub2apiService::class);
    $service->shouldReceive('listUsage')->once()->with(1, 0)->andThrow($exception);
    foreach (range(2, 101) as $key_id) {
        $service->shouldReceive('listUsage')->once()->with($key_id, 0)->andReturn([]);
    }
    $service->shouldReceive('getKeepActiveThreshold')->times(100)->andReturn(0.0);
    app()->instance(Sub2apiService::class, $service);

    $this->artisan('app:sync-sub2api-command')->assertFailed();

    Exceptions::assertReported(fn (RuntimeException $reported) => $reported === $exception);
    expect($users->first()->refresh()->sub2api_key_status)->toBe(Sub2apiKeyService::STATUS_ACTIVE);
});

test('sub2api sync succeeds when every user sync succeeds', function () {
    $user = User::factory()->create([
        'sub2api_key_id' => 456,
        'sub2api_key_status' => Sub2apiKeyService::STATUS_INACTIVE,
        'balance' => 5,
    ]);
    $service = Mockery::mock(Sub2apiService::class);
    $service->shouldReceive('listUsage')->once()->with(456, 0)->andReturn([]);
    $service->shouldReceive('getKeepActiveThreshold')->once()->andReturn(0.0);
    $service->shouldReceive('updateKeyStatus')->once()->with(456, Sub2apiKeyService::STATUS_ACTIVE)->andReturn([]);
    app()->instance(Sub2apiService::class, $service);

    $this->artisan('app:sync-sub2api-command')->assertSuccessful();

    expect($user->refresh()->sub2api_key_status)->toBe(Sub2apiKeyService::STATUS_ACTIVE);
});

test('sub2api sync succeeds when no users have remote keys', function () {
    $this->artisan('app:sync-sub2api-command')->assertSuccessful();
});
