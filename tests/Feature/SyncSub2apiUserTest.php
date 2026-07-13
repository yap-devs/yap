<?php

use App\Jobs\SyncSub2apiUser;
use App\Models\User;
use App\Services\Sub2apiKeyService;
use App\Services\Sub2apiService;

test('sync sub2api user job syncs usage and key status', function () {
    config()->set('services.sub2api.enabled', true);
    config()->set('services.sub2api.min_balance_to_keep_active', 0);

    $sub2api_service = Mockery::mock(Sub2apiService::class);
    $sub2api_service->shouldReceive('listUsage')
        ->once()
        ->with(456, 0)
        ->andReturn([]);
    $sub2api_service->shouldReceive('getKeepActiveThreshold')
        ->once()
        ->andReturn(0.0);
    $sub2api_service->shouldReceive('updateKeyStatus')
        ->once()
        ->with(456, Sub2apiKeyService::STATUS_ACTIVE)
        ->andReturn([]);
    app()->instance(Sub2apiService::class, $sub2api_service);

    $user = User::factory()->create(['balance' => 5]);
    $user->forceFill([
        'sub2api_key_id' => 456,
        'sub2api_key_status' => Sub2apiKeyService::STATUS_INACTIVE,
    ])->save();

    (new SyncSub2apiUser($user->id))->handle(app(Sub2apiKeyService::class));

    expect($user->refresh()->sub2api_key_status)->toBe(Sub2apiKeyService::STATUS_ACTIVE);
});
