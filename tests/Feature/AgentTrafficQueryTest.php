<?php

use App\Models\Node;
use App\Models\NodeRoute;
use App\Models\Package;
use App\Models\TrafficRecord;
use App\Models\User;
use App\Models\UserPackage;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

test('package reads stay bounded across a multi-user traffic batch with exact balance settlement', function () {
    config(['node_agent.enabled' => true, 'yap.unit_price' => '0.02']);
    Bus::fake();
    Notification::fake();
    Http::fake();
    Http::preventStrayRequests();
    $token = Str::random(64);
    $node = Node::factory()->create(['agent_token_hash' => hash('sha256', $token)]);
    $route = NodeRoute::factory()->for($node)->create(['rate' => '1.00']);
    $counts = [];
    $gib = 1073741824;

    foreach ([1, 10] as $count) {
        $users = User::factory()->count($count)->create(['balance' => '0.01', 'github_created_at' => null]);
        $records = $users->map(fn (User $user): array => ['user_id' => $user->id, 'route_id' => $route->id, 'uplink' => 0, 'downlink' => $gib + 1])->all();
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $this->withToken($token)->postJson('/api/agent/v1/traffic', ['batch_uuid' => (string) Str::uuid(), 'records' => $records])->assertOk();
            $counts[$count] = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_starts_with(strtolower(ltrim($query['query'])), 'select') && str_contains($query['query'], 'user_packages'))->count();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
        foreach ($users as $user) {
            $user->refresh();
            expect($user->balance)->toBe('-0.01')
                ->and((int) $user->traffic_downlink)->toBe($gib + 1)
                ->and((int) $user->traffic_unpaid)->toBe(1)
                ->and($user->balanceDetails()->count())->toBe(1)
                ->and(bccomp((string) $user->balanceDetails()->first()->amount, '-0.02', 2))->toBe(0);
        }
    }

    expect(TrafficRecord::count())->toBe(11)
        ->and($counts[10])->toBeLessThanOrEqual($counts[1])
        ->and($counts[10])->toBeLessThanOrEqual(2);
    Http::assertNothingSent();
});

test('package continuation stays bounded for ten users and consumes loaded queued packages', function () {
    $this->travelTo(now()->startOfSecond());
    config(['node_agent.enabled' => true]);
    Bus::fake();
    Notification::fake();
    Http::fake();
    Http::preventStrayRequests();
    $token = Str::random(64);
    $node = Node::factory()->create(['agent_token_hash' => hash('sha256', $token)]);
    $route = NodeRoute::factory()->for($node)->create(['rate' => '1.00']);
    $package = Package::create(['name' => 'Test continuation', 'price' => 1, 'traffic_limit' => 1000, 'duration_days' => 30, 'status' => Package::STATUS_ACTIVE]);
    $counts = [];

    foreach ([1, 10] as $count) {
        $users = User::factory()->count($count)->create(['balance' => '0.00', 'github_created_at' => null]);
        $packages = [];
        foreach ($users as $user) {
            $packages[$user->id] = [
                $user->packages()->create(['package_id' => $package->id, 'status' => UserPackage::STATUS_ACTIVE, 'remaining_traffic' => 100, 'started_at' => now()->subDay(), 'ended_at' => now()->addDay()]),
                $user->packages()->create(['package_id' => $package->id, 'status' => UserPackage::STATUS_ACTIVE, 'remaining_traffic' => 1000, 'started_at' => now()->addDay(), 'ended_at' => now()->addDays(31)]),
            ];
        }
        $records = $users->map(fn (User $user): array => ['user_id' => $user->id, 'route_id' => $route->id, 'uplink' => 0, 'downlink' => 250])->all();
        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $this->withToken($token)->postJson('/api/agent/v1/traffic', ['batch_uuid' => (string) Str::uuid(), 'records' => $records])->assertOk();
            $counts[$count] = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_starts_with(strtolower(ltrim($query['query'])), 'select') && str_contains($query['query'], 'user_packages'))->count();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
        foreach ($users as $user) {
            [$used, $next] = $packages[$user->id];
            expect($used->fresh()->status)->toBe(UserPackage::STATUS_USED)
                ->and((int) $used->fresh()->remaining_traffic)->toBe(0)
                ->and((int) $next->fresh()->remaining_traffic)->toBe(850)
                ->and($next->fresh()->isStarted())->toBeTrue()
                ->and($next->fresh()->ended_at->equalTo(now()->addDays(30)))->toBeTrue()
                ->and($user->fresh()->balance)->toBe('0.00')
                ->and((int) $user->fresh()->traffic_unpaid)->toBe(0);
        }
    }

    expect($counts[10])->toBeLessThanOrEqual($counts[1])
        ->and($counts[10])->toBeLessThanOrEqual(2);
    Http::assertNothingSent();
});

test('multiple routes accumulate once per user and replay preserves billed details', function () {
    config(['node_agent.enabled' => true]);
    Bus::fake();
    Notification::fake();
    Http::fake();
    Http::preventStrayRequests();
    $token = Str::random(64);
    $node = Node::factory()->create(['agent_token_hash' => hash('sha256', $token)]);
    $first = NodeRoute::factory()->for($node)->create(['rate' => '1.25', 'listen_port' => 10000]);
    $second = NodeRoute::factory()->for($node)->create(['rate' => '3.00', 'listen_port' => 10001]);
    $user = User::factory()->create(['balance' => '1.00', 'github_created_at' => null]);
    $payload = ['batch_uuid' => (string) Str::uuid(), 'records' => [
        ['user_id' => $user->id, 'route_id' => $first->id, 'uplink' => 1, 'downlink' => 100],
        ['user_id' => $user->id, 'route_id' => $second->id, 'uplink' => 1, 'downlink' => 200],
    ]];

    $this->withToken($token)->postJson('/api/agent/v1/traffic', $payload)->assertOk();
    $first->update(['rate' => '9.00']);
    $this->postJson('/api/agent/v1/traffic', $payload)->assertOk();

    expect((int) $user->fresh()->traffic_uplink)->toBe(4)
        ->and((int) $user->fresh()->traffic_downlink)->toBe(725)
        ->and((int) $user->fresh()->traffic_unpaid)->toBe(729)
        ->and($user->fresh()->balance)->toBe('1.00')
        ->and(TrafficRecord::count())->toBe(2)
        ->and(TrafficRecord::where('node_route_id', $first->id)->first()->applied_rate)->toBe('1.25')
        ->and((int) TrafficRecord::where('node_route_id', $second->id)->first()->billed_downlink)->toBe(600);
    Http::assertNothingSent();
});
