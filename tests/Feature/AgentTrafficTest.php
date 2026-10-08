<?php

use App\Models\Node;
use App\Models\NodeRoute;
use App\Models\TrafficBatch;
use App\Models\TrafficRecord;
use App\Models\User;
use App\Models\UserStat;
use App\Services\TrafficAggregationService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['node_agent.enabled' => true]);
    Notification::fake();
    $this->token = Str::random(64);
    $this->node = Node::factory()->create(['agent_token_hash' => hash('sha256', $this->token)]);
    $this->route = NodeRoute::factory()->for($this->node)->create(['rate' => '1.25']);
    $this->user = User::factory()->create(['balance' => 10]);
    $this->payload = ['batch_uuid' => (string) Str::uuid(), 'records' => [
        ['user_id' => $this->user->id, 'route_id' => $this->route->id, 'uplink' => 101, 'downlink' => 202],
    ]];
});

test('traffic is charged once and retries retain the original multiplier', function () {
    $this->withToken($this->token)->postJson('/api/agent/v1/traffic', $this->payload)->assertOk();
    $this->route->update(['rate' => '3.00']);
    $this->withToken($this->token)->postJson('/api/agent/v1/traffic', $this->payload)->assertOk();

    expect(TrafficBatch::count())->toBe(1)->and(TrafficRecord::count())->toBe(1)
        ->and($this->user->fresh()->traffic_uplink)->toBe(126)
        ->and($this->user->fresh()->traffic_downlink)->toBe(252)
        ->and(TrafficRecord::first()->applied_rate)->toBe('1.25')
        ->and(UserStat::count())->toBe(0);
});

test('changed retry contents are rejected without another charge', function () {
    $this->withToken($this->token)->postJson('/api/agent/v1/traffic', $this->payload)->assertOk();
    $this->payload['records'][0]['uplink'] = 999;
    $this->postJson('/api/agent/v1/traffic', $this->payload)->assertConflict();
    expect(TrafficRecord::count())->toBe(1);
});

test('foreign routes roll back the entire batch', function () {
    $this->payload['records'][] = ['user_id' => $this->user->id, 'route_id' => NodeRoute::factory()->create()->id, 'uplink' => 1, 'downlink' => 1];
    $this->withToken($this->token)->postJson('/api/agent/v1/traffic', $this->payload)->assertUnprocessable();
    expect(TrafficBatch::count())->toBe(0)->and(TrafficRecord::count())->toBe(0)
        ->and($this->user->fresh()->traffic_uplink)->toBe(0);
});

test('anonymous and disabled agents cannot submit traffic', function () {
    $this->postJson('/api/agent/v1/traffic', $this->payload)->assertUnauthorized();
    $this->node->update(['enabled' => false]);
    $this->withToken($this->token)->postJson('/api/agent/v1/traffic', $this->payload)->assertUnauthorized();
});

test('duplicate rows and invalid counters are rejected', function () {
    $this->payload['records'][] = $this->payload['records'][0];
    $this->withToken($this->token)->postJson('/api/agent/v1/traffic', $this->payload)->assertUnprocessable();
    $this->payload['records'] = [$this->payload['records'][0]];
    $this->payload['records'][0]['uplink'] = -1;
    $this->postJson('/api/agent/v1/traffic', $this->payload)->assertUnprocessable();
});

test('hourly aggregation catches up and does not charge again', function () {
    $this->travelTo(now()->startOfHour()->subHours(2)->addMinutes(15));
    $this->withToken($this->token)->postJson('/api/agent/v1/traffic', $this->payload)->assertOk();
    $hour = now()->startOfHour();
    $this->travel(3)->hours();
    app(TrafficAggregationService::class)->aggregate();
    app(TrafficAggregationService::class)->aggregate();

    expect(UserStat::count())->toBe(1)->and(UserStat::first()->traffic_uplink)->toBe(126)
        ->and(UserStat::first()->created_at->equalTo($hour))->toBeTrue()
        ->and($this->user->fresh()->traffic_uplink)->toBe(126)
        ->and(TrafficBatch::first()->aggregated_at)->not->toBeNull();
});

test('retention purges only aggregated details and keeps deduplication receipts', function () {
    $this->travelTo(now()->subDays(10));
    $this->withToken($this->token)->postJson('/api/agent/v1/traffic', $this->payload)->assertOk();
    $this->travel(10)->days();
    $this->artisan('nodes:prune-traffic')->assertSuccessful();
    expect(TrafficRecord::count())->toBe(1);
    app(TrafficAggregationService::class)->aggregate();
    $this->artisan('nodes:prune-traffic')->assertSuccessful();
    expect(TrafficRecord::withTrashed()->count())->toBe(0)->and(TrafficBatch::count())->toBe(1);
    $this->withToken($this->token)->postJson('/api/agent/v1/traffic', $this->payload)->assertOk();
    expect($this->user->fresh()->traffic_uplink)->toBe(126);
});
