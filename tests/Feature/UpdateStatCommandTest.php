<?php

use App\Models\User;
use App\Services\TrafficReportSnapshotService;
use Illuminate\Support\Facades\Notification;

test('account maintenance preserves agent counters when snapshot refresh fails', function (bool $snapshot_fails) {
    Notification::fake();
    $this->travelTo(now()->startOfDay()->addHours(12));
    $user = User::factory()->create(['balance' => 10, 'github_created_at' => null, 'traffic_uplink' => 100, 'traffic_downlink' => 200, 'traffic_unpaid' => 300]);
    $snapshots = Mockery::mock(TrafficReportSnapshotService::class);
    if ($snapshot_fails) {
        $snapshots->shouldReceive('refresh')->once()->andThrow(new RuntimeException('snapshot unavailable'));
    } else {
        $snapshots->shouldReceive('refresh')->once()->andReturnTrue();
    }
    app()->instance(TrafficReportSnapshotService::class, $snapshots);
    $this->artisan('app:update-stat-command')->assertSuccessful();
    expect((int) $user->fresh()->traffic_uplink)->toBe(100)
        ->and((int) $user->fresh()->traffic_downlink)->toBe(200)
        ->and((int) $user->fresh()->traffic_unpaid)->toBe(300);
})->with([false, true]);

test('daily remainder settlement survives legacy collector retirement and is not charged twice', function () {
    Notification::fake();
    $this->travelTo(now()->startOfDay()->addMinutes(2));
    $user = User::factory()->create(['balance' => 10, 'github_created_at' => null, 'traffic_unpaid' => 300]);
    $this->artisan('app:update-stat-command')->assertSuccessful();
    $this->artisan('app:update-stat-command')->assertSuccessful();
    expect((float) $user->fresh()->balance)->toBe(9.98)
        ->and((int) $user->fresh()->traffic_unpaid)->toBe(0)
        ->and($user->balanceDetails()->where('amount', '<', 0)->count())->toBe(1);
});
