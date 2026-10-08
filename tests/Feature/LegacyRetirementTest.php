<?php

use App\Models\Node;
use App\Models\NodeRoute;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('fresh installation omits obsolete tables while active queue storage remains', function () {
    foreach (['vmess_servers', 'relay_servers', 'job_batches'] as $table) {
        expect(Schema::hasTable($table))->toBeFalse();
    }
    expect(Schema::hasTable('jobs'))->toBeTrue()
        ->and(Schema::hasTable('failed_jobs'))->toBeTrue();
});

test('dashboard and subscriptions use only enabled node routes', function () {
    $this->withoutVite();
    $user = User::factory()->create(['balance' => 10, 'uuid' => (string) Str::uuid()]);
    $node = Node::factory()->create();
    $route = NodeRoute::factory()->for($node)->create(['name' => 'New route', 'rate' => '1.25']);
    NodeRoute::factory()->for(Node::factory()->create(['enabled' => false]))->create(['name' => 'Disabled node']);
    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertInertia(fn ($page) => $page
        ->where('servers.0.id', $route->id)->where('servers.0.name', 'New route')->has('servers', 1));
    expect(app(SubscriptionService::class)->content($user, 'clash'))->toContain('New route[1.25x]')->not->toContain('Disabled node');
});

test('fresh installation creates the agent traffic schema', function () {
    foreach (['nodes', 'node_routes', 'traffic_batches', 'traffic_records'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
    expect(Schema::hasColumn('nodes', 'legacy_internal_server'))->toBeFalse();
    $node_id = DB::table('nodes')->insertGetId(['name' => 'Fresh agent node']);
    expect(Schema::hasColumn('nodes', 'traffic_source'))->toBeFalse()
        ->and(DB::table('nodes')->where('id', $node_id)->value('enabled'))->toBe(0);
});

test('fresh installation does not execute a legacy creation or retirement path', function () {
    expect(DB::table('migrations')->pluck('migration')->all())
        ->not->toContain('2024_06_26_160818_create_vmess_servers_table')
        ->not->toContain('2024_11_13_141041_create_relay_servers_table')
        ->not->toContain('2026_10_07_123257_retire_legacy_node_tables');
});

test('fresh installation retains database queue storage', function () {
    $payload = json_encode(['displayName' => 'Illuminate\\Notifications\\SendQueuedNotifications']);
    $job_id = DB::table('jobs')->insertGetId([
        'queue' => 'default',
        'payload' => $payload,
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->timestamp,
        'created_at' => now()->timestamp,
    ]);

    expect(DB::table('jobs')->where('id', $job_id)->value('payload'))->toBe($payload);
});

test('fresh installation retains failed notification storage', function () {
    $payload = json_encode(['displayName' => 'Illuminate\\Notifications\\SendQueuedNotifications']);
    DB::table('failed_jobs')->insert([
        'uuid' => 'failed-notification',
        'connection' => 'database',
        'queue' => 'default',
        'payload' => $payload,
        'exception' => 'notification failure',
        'failed_at' => now(),
    ]);

    expect(DB::table('failed_jobs')->where('uuid', 'failed-notification')->value('payload'))->toBe($payload);
});
