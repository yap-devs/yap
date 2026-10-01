<?php

use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

test('traffic chart sums daily records in chronological order within its existing window', function () {
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2026-01-02 12:00:00'));
    $user = User::factory()->create(['balance' => 10]);
    foreach ([
        ['2026-01-02 00:00:00', 3, 30],
        ['2025-12-31 23:59:59', 2, 20],
        ['2025-12-19 00:00:00', 1, 10],
        ['2025-12-31 00:00:00', 4, 40],
        ['2025-12-18 23:59:59', 100, 1000],
    ] as [$created_at, $uplink, $downlink]) {
        $user->stats()->create(['created_at' => $created_at, 'traffic_uplink' => $uplink, 'traffic_downlink' => $downlink]);
    }
    $user->stats()->create(['created_at' => '2025-12-31 12:00:00', 'traffic_uplink' => 100, 'traffic_downlink' => 1000])->delete();
    User::factory()->create()->stats()->create(['traffic_uplink' => 100, 'traffic_downlink' => 1000]);

    $this->actingAs($user)->get(route('stat'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Stat/Index')
        ->where('chartData.labels', ['12/19', '12/31', '01/02'])
        ->where('chartData.datasets.0.data', [10, 60, 30])
        ->where('chartData.datasets.1.data', [1, 6, 3])
    );
});

test('traffic chart remains empty for users without valid access', function () {
    $this->withoutVite();
    $user = User::factory()->create(['balance' => 0]);
    $user->stats()->create(['traffic_uplink' => 10, 'traffic_downlink' => 20]);

    $this->actingAs($user)->get(route('stat'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('chartData', [])
    );
});
