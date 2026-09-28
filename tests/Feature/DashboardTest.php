<?php

use App\Models\User;
use App\Models\VmessServer;
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
