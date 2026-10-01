<?php

use App\Models\User;
use App\Models\VmessServer;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

test('dashboard only exposes public display fields for enabled servers', function () {
    $user = User::factory()->create([
        'id' => 2,
        'uuid' => '6cfcfc20-1809-4894-bc0c-93f5ecf39026',
    ]);
    $server = VmessServer::create([
        'name' => 'Public node',
        'server' => 'proxy.example.com',
        'port' => 443,
        'internal_server' => '10.0.0.5:2222',
        'rate' => 1.5,
        'enabled' => true,
        'for_low_priority' => 1,
    ]);
    VmessServer::create([
        'name' => 'Disabled node',
        'port' => 443,
        'internal_server' => '10.0.0.6:2222',
        'enabled' => false,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('servers', 1)
            ->has('servers.0', fn (Assert $server_props) => $server_props
                ->where('id', $server->id)
                ->where('name', 'Public node')
                ->where('rate', 1.5)
                ->where('for_low_priority', 1)
                ->missing('internal_server')
                ->missing('server')
                ->missing('port')
            )
        );
});

test('dashboard traffic excludes other days deleted records and other users', function () {
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2026-01-02 12:00:00'));
    $user = User::factory()->create(['balance' => 10, 'uuid' => 'test-dashboard-uuid']);
    foreach (['2026-01-01 23:59:59', '2026-01-02 00:00:00', '2026-01-02 23:59:59', '2026-01-03 00:00:00'] as $created_at) {
        $user->stats()->create(['created_at' => $created_at, 'traffic_uplink' => 10, 'traffic_downlink' => 20]);
    }
    $user->stats()->create(['traffic_uplink' => 100, 'traffic_downlink' => 200])->delete();
    User::factory()->create()->stats()->create(['traffic_uplink' => 100, 'traffic_downlink' => 200]);

    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('todayTraffic', 60));
});

test('dashboard traffic cache changes at midnight before its ttl expires', function () {
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2026-01-01 23:55:00'));
    $user = User::factory()->create(['balance' => 10, 'uuid' => 'test-dashboard-uuid']);
    $user->stats()->create(['traffic_uplink' => 10, 'traffic_downlink' => 20]);
    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('todayTraffic', 30));

    $this->travelTo(CarbonImmutable::parse('2026-01-02 00:05:00'));
    $user->stats()->create(['traffic_uplink' => 20, 'traffic_downlink' => 40]);
    $this->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('todayTraffic', 60));
});
