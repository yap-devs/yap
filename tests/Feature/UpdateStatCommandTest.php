<?php

use App\Models\User;
use App\Models\VmessServer;
use App\Services\TrafficReportSnapshotService;
use App\Services\V2rayService;
use Illuminate\Support\Facades\Cache;

test('traffic statistics use stable user labels and support legacy email labels', function (bool $snapshot_fails) {
    $this->travelTo(now()->startOfDay()->addHours(12));

    $stable_label_user = User::factory()->create([
        'uuid' => 'uuid-user-id',
        'balance' => 10,
    ]);
    $legacy_user = User::factory()->create([
        'uuid' => 'legacy-user-id',
        'balance' => 10,
    ]);
    $stable_label = $stable_label_user->v2rayStatsLabel();
    $stable_label_user->update([
        'uuid' => 'rotated-user-id',
        'email' => 'rotated@example.com',
    ]);
    VmessServer::create([
        'name' => 'Test node',
        'server' => 'node.example.com',
        'port' => 443,
        'rate' => 1,
        'internal_server' => 'internal.example.com',
        'enabled' => true,
        'for_low_priority' => false,
    ]);

    $v2ray = Mockery::mock(V2rayService::class);
    $v2ray->shouldReceive('getStats')->once()->with(true)->andReturn([
        'user' => [
            $stable_label => [
                'uplink' => 100,
                'downlink' => 200,
            ],
            $legacy_user->email => [
                'uplink' => 300,
                'downlink' => 400,
            ],
        ],
    ]);
    app()->bind(V2rayService::class, function ($app, array $parameters) use ($v2ray) {
        expect($parameters['internal_server'])->toBe('internal.example.com');

        return $v2ray;
    });

    $snapshots = Mockery::mock(TrafficReportSnapshotService::class);
    if ($snapshot_fails) {
        $snapshots->shouldReceive('refresh')->once()->andThrow(new RuntimeException('snapshot refresh failed'));
    } else {
        $snapshots->shouldReceive('refresh')->once()->andReturnTrue();
    }
    app()->instance(TrafficReportSnapshotService::class, $snapshots);

    $traffic_cache_key = 'today_traffic_'.$stable_label_user->id.'_'.now()->toDateString();
    Cache::put($traffic_cache_key, 0, 1800);

    $this->artisan('app:update-stat-command')->assertSuccessful();

    expect(Cache::has($traffic_cache_key))->toBeFalse();

    $stable_label_user->refresh();
    $legacy_user->refresh();

    expect($stable_label_user->v2rayStatsLabel())->toBe($stable_label)
        ->and((int) $stable_label_user->traffic_uplink)->toBe(100)
        ->and((int) $stable_label_user->traffic_downlink)->toBe(200)
        ->and((int) $stable_label_user->traffic_unpaid)->toBe(300)
        ->and((int) $legacy_user->traffic_uplink)->toBe(300)
        ->and((int) $legacy_user->traffic_downlink)->toBe(400)
        ->and((int) $legacy_user->traffic_unpaid)->toBe(700);
})->with([
    'snapshot refreshed' => [false],
    'snapshot unavailable' => [true],
]);
