<?php

use App\Models\Node;
use App\Models\NodeRoute;
use App\Models\TrafficBatch;
use App\Models\TrafficRecord;
use App\Models\User;
use App\Services\TrafficBillingService;
use App\Services\TrafficIngestionService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Notification::fake();
    config(['yap.unit_price' => '0.02']);
});

test('settlement retains the strict gib boundary', function (int $bytes, string $balance, int $remaining) {
    $user = User::factory()->create(['balance' => '1.00', 'traffic_unpaid' => $bytes]);
    app(TrafficBillingService::class)->settle($user);
    expect($user->fresh()->balance)->toBe($balance)
        ->and((int) $user->fresh()->traffic_unpaid)->toBe($remaining);
})->with([
    [1073741824, '1.00', 1073741824],
    [1073741825, '0.98', 1],
    [2147483648, '0.98', 1073741824],
    [2147483649, '0.96', 1],
]);

test('large settlement uses exact decimal arithmetic and one ledger row', function () {
    config(['yap.unit_price' => '0.01']);
    $user = User::factory()->create(['balance' => '999999.99', 'traffic_unpaid' => 90000000 * 1073741824 + 1]);
    app(TrafficBillingService::class)->settle($user);
    expect($user->fresh()->balance)->toBe('99999.99')
        ->and((int) $user->fresh()->traffic_unpaid)->toBe(1)
        ->and($user->balanceDetails()->count())->toBe(1)
        ->and(bccomp((string) $user->balanceDetails()->first()->amount, '-900000.00', 2))->toBe(0);
});

test('balance overflow rolls back the entire incoming batch', function () {
    $node = Node::factory()->create();
    $route = NodeRoute::factory()->for($node)->create(['rate' => '1.00']);
    $user = User::factory()->create(['balance' => '-999999.98']);
    expect(fn () => app(TrafficIngestionService::class)->ingest($node, (string) Str::uuid(), [
        ['user_id' => $user->id, 'route_id' => $route->id, 'uplink' => 1073741825, 'downlink' => 0],
    ]))->toThrow(ValidationException::class);
    expect(TrafficBatch::count())->toBe(0)->and(TrafficRecord::count())->toBe(0)
        ->and($user->fresh()->balance)->toBe('-999999.98')
        ->and((int) $user->fresh()->traffic_uplink)->toBe(0)
        ->and((int) $user->fresh()->traffic_unpaid)->toBe(0)
        ->and($user->balanceDetails()->count())->toBe(0);
});

test('maximum counter settles in constant work when traffic is free', function () {
    config(['yap.unit_price' => '0.00']);
    $user = User::factory()->create(['balance' => '1.00', 'traffic_unpaid' => PHP_INT_MAX]);
    app(TrafficBillingService::class)->settle($user);
    expect($user->fresh()->balance)->toBe('1.00')
        ->and((int) $user->fresh()->traffic_unpaid)->toBe(1073741823);
});

test('ledger overflow is rejected even when final balance fits', function () {
    config(['yap.unit_price' => '1.00']);
    $user = User::factory()->create(['balance' => '999999.99', 'traffic_unpaid' => 1000000 * 1073741824 + 1]);
    expect(fn () => app(TrafficBillingService::class)->settle($user))->toThrow(ValidationException::class);
    expect($user->fresh()->balance)->toBe('999999.99')
        ->and((int) $user->fresh()->traffic_unpaid)->toBe(1000000 * 1073741824 + 1)
        ->and($user->balanceDetails()->count())->toBe(0);
});
