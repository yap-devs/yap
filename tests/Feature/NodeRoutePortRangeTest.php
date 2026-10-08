<?php

use App\Models\Node;
use App\Models\NodeRoute;
use Illuminate\Validation\ValidationException;

test('disabling a middle shared-handler route is rejected but edge removal is allowed', function () {
    $node = Node::factory()->create();
    $routes = collect([10000, 10001, 10002])->map(fn (int $port) => NodeRoute::factory()->for($node)->create(['inbound_tag' => 'yap-shared', 'listen_port' => $port]));
    expect(fn () => $routes[1]->update(['enabled' => false]))->toThrow(ValidationException::class);
    expect($routes[1]->fresh()->enabled)->toBeTrue();
    $routes[2]->update(['enabled' => false]);
    $routes[1]->refresh()->update(['enabled' => false]);
    expect($routes[0]->fresh()->enabled)->toBeTrue()->and($routes[1]->fresh()->enabled)->toBeFalse();
});

test('disabled gaps are permitted but cannot be enabled until contiguous', function () {
    $node = Node::factory()->create();
    NodeRoute::factory()->for($node)->create(['inbound_tag' => 'yap-shared', 'listen_port' => 10000]);
    $gap = NodeRoute::factory()->for($node)->create(['inbound_tag' => 'yap-shared', 'listen_port' => 10002, 'enabled' => false]);
    expect(fn () => $gap->update(['enabled' => true]))->toThrow(ValidationException::class);
    NodeRoute::factory()->for($node)->create(['inbound_tag' => 'yap-shared', 'listen_port' => 10001]);
    $gap->refresh()->update(['enabled' => true]);
    expect($gap->fresh()->enabled)->toBeTrue();
});

test('deleting a middle shared-handler route is rejected but edge deletion is allowed', function () {
    $node = Node::factory()->create();
    $routes = collect([10000, 10001, 10002])->map(fn (int $port) => NodeRoute::factory()->for($node)->create(['inbound_tag' => 'yap-shared', 'listen_port' => $port]));

    expect(fn () => $routes[1]->delete())->toThrow(ValidationException::class);
    $this->assertNotSoftDeleted($routes[1]);

    $routes[2]->delete();
    $routes[1]->delete();

    $this->assertSoftDeleted($routes[2]);
    $this->assertSoftDeleted($routes[1]);
    $this->assertNotSoftDeleted($routes[0]);
});

test('restoring an enabled route cannot create a gap in shared-handler ports', function () {
    $node = Node::factory()->create();
    NodeRoute::factory()->for($node)->create(['inbound_tag' => 'yap-shared', 'listen_port' => 10000]);
    $middle = NodeRoute::factory()->for($node)->create(['inbound_tag' => 'yap-shared', 'listen_port' => 10001]);
    $edge = NodeRoute::factory()->for($node)->create(['inbound_tag' => 'yap-shared', 'listen_port' => 10002]);
    $edge->delete();
    $middle->delete();

    expect(fn () => $edge->restore())->toThrow(ValidationException::class);
    $this->assertSoftDeleted($edge);

    $middle->restore();
    $edge->refresh()->restore();
    $this->assertNotSoftDeleted($middle);
    $this->assertNotSoftDeleted($edge);
});

test('deleting a disabled shared-handler route does not remove an active port', function () {
    $node = Node::factory()->create();
    NodeRoute::factory()->for($node)->create(['inbound_tag' => 'yap-shared', 'listen_port' => 10000]);
    $disabled = NodeRoute::factory()->for($node)->create(['inbound_tag' => 'yap-shared', 'listen_port' => 10002, 'enabled' => false]);

    $disabled->delete();

    $this->assertSoftDeleted($disabled);
});
