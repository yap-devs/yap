<?php

use App\Jobs\GenerateClashProfileLink;
use App\Jobs\SyncSub2apiUser;
use App\Models\Payment;
use App\Models\User;
use App\Services\Sub2apiKeyService;
use App\Services\Sub2apiService;
use Illuminate\Support\Facades\Bus;

test('github sponsor recharge dispatches sub2api user sync job', function () {
    Bus::fake();

    config()->set('yap.github.webhook_secret', 'github-secret');
    config()->set('services.sub2api.enabled', true);
    config()->set('services.sub2api.min_balance_to_keep_active', 0);

    $sub2api_service = Mockery::mock(Sub2apiService::class);
    $sub2api_service->shouldReceive('listUsage')
        ->never();
    $sub2api_service->shouldReceive('getKeepActiveThreshold')
        ->never();
    $sub2api_service->shouldReceive('updateKeyStatus')
        ->never();
    app()->instance(Sub2apiService::class, $sub2api_service);

    $user = User::factory()->create(['balance' => 0]);
    $user->forceFill([
        'github_id' => '12345',
        'sub2api_key_id' => 321,
        'sub2api_key_status' => Sub2apiKeyService::STATUS_INACTIVE,
    ])->save();

    $payload = [
        'action' => 'created',
        'sponsorship' => [
            'sponsor' => [
                'id' => '12345',
            ],
            'tier' => [
                'monthly_price_in_dollars' => 5,
                'node_id' => 'tier-node',
                'created_at' => '2026-07-13T00:00:00Z',
            ],
        ],
    ];
    $json_payload = json_encode($payload);

    $response = $this->call('POST', route('github.sponsor_webhook'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE' => 'sha1='.hash_hmac('sha1', $json_payload, 'github-secret'),
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $json_payload, 'github-secret'),
    ], $json_payload);

    $response->assertOk();

    expect($user->refresh()->sub2api_key_status)->toBe(Sub2apiKeyService::STATUS_INACTIVE)
        ->and((float) $user->balance)->toBe(5.0);

    $payment = Payment::query()->where('remote_id', 'tier-node|2026-07-13T00:00:00Z')->first();

    expect($payment)->not->toBeNull()
        ->and($payment->status)->toBe(Payment::STATUS_PAID);

    expect($payment->payload)->toHaveKey('action');
    expect($payment->payload['action'])->toBe('created');
    $this->assertArrayNotHasKey(Payment::STATUS_CREATED, $payment->payload);

    $this->assertDatabaseHas('payments', [
        'user_id' => $user->id,
        'gateway' => Payment::GATEWAY_GITHUB,
        'status' => Payment::STATUS_PAID,
        'amount' => 5,
        'remote_id' => 'tier-node|2026-07-13T00:00:00Z',
    ]);

    Bus::assertDispatched(GenerateClashProfileLink::class);
    Bus::assertDispatched(SyncSub2apiUser::class, fn (SyncSub2apiUser $job): bool => $job->user_id === $user->id);
});
