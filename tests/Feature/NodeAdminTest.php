<?php

use App\Filament\Resources\NodeRoutes\Pages\ManageNodeRoutes;
use App\Filament\Resources\Nodes\Pages\ManageNodes;
use App\Filament\Resources\TrafficRecords\Pages\ManageTrafficRecords;
use App\Models\Node;
use App\Models\NodeRoute;
use App\Models\TrafficRecord;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

test('admin can inspect node routes and billing records without exposing tokens', function () {
    config(['node_agent.enabled' => true]);
    $this->actingAs(User::factory()->create(['id' => 1]));
    $node = Node::factory()->create();
    $route = NodeRoute::factory()->for($node)->create();
    $record = TrafficRecord::factory()->create();
    Livewire::test(ManageNodes::class)->assertOk()->assertCanSeeTableRecords([$node])->assertDontSee($node->agent_token_hash);
    Livewire::test(ManageNodeRoutes::class)->assertOk()->assertCanSeeTableRecords([$route]);
    Livewire::test(ManageTrafficRecords::class)->assertOk()->assertCanSeeTableRecords([$record]);
});

test('admin creates a disabled node with object configuration and its direct route', function () {
    config(['node_agent.enabled' => true]);
    $this->actingAs(User::factory()->create(['id' => 1]));
    Livewire::test(ManageNodes::class)
        ->callAction('create', ['name' => 'Direct node', 'enabled' => false, 'core_config' => '{"inbounds":[],"outbounds":[]}'])
        ->assertHasNoActionErrors();
    $node = Node::where('name', 'Direct node')->firstOrFail();
    expect($node->enabled)->toBeFalse()->and($node->getAttributes())->not->toHaveKeys(['traffic_source', 'legacy_internal_server']);
    Livewire::test(ManageNodeRoutes::class)
        ->callAction('create', ['node_id' => $node->id, 'name' => 'Direct entry', 'server' => 'node.example.com', 'port' => 443,
            'inbound_tag' => 'yap-main', 'listen_port' => 443, 'rate' => '1.25', 'sort' => 0, 'enabled' => false, 'for_low_priority' => false])
        ->assertHasNoActionErrors();
    expect($node->routes()->firstOrFail()->rate)->toBe('1.25');
});

test('admin cannot save scalar core configuration', function () {
    config(['node_agent.enabled' => true]);
    $this->actingAs(User::factory()->create(['id' => 1]));
    Livewire::test(ManageNodes::class)
        ->callAction('create', ['name' => 'Invalid node', 'core_config' => 'null'])
        ->assertHasActionErrors(['core_config']);
    expect(Node::where('name', 'Invalid node')->exists())->toBeFalse();
});

test('admin create and edit preserve core configuration json structure', function () {
    config(['node_agent.enabled' => true]);
    $this->actingAs(User::factory()->create(['id' => 1]));
    $core_config = '{"policy":{"levels":{"0":{"bufferSize":4,"handshake":8}}},"outbounds":[{"protocol":"blackhole","settings":{}}],"routing":{"rules":[]}}';

    Livewire::test(ManageNodes::class)
        ->callAction('create', ['name' => 'Structured node', 'enabled' => false, 'core_config' => $core_config])
        ->assertHasNoActionErrors();
    $node = Node::where('name', 'Structured node')->firstOrFail();
    expect(json_encode($node->core_config))->toBe($core_config);

    Livewire::test(ManageNodes::class)
        ->callAction(TestAction::make('edit')->table($node), ['name' => 'Edited node'])
        ->assertHasNoActionErrors();

    expect($node->fresh()->name)->toBe('Edited node')
        ->and(json_encode($node->fresh()->core_config))->toBe($core_config);
});

test('admin cannot create the reserved management inbound', function () {
    config(['node_agent.enabled' => true]);
    $this->actingAs(User::factory()->create(['id' => 1]));
    $node = Node::factory()->create();
    Livewire::test(ManageNodeRoutes::class)
        ->callAction('create', ['node_id' => $node->id, 'name' => 'Reserved entry', 'server' => 'node.example.com', 'port' => 443,
            'inbound_tag' => 'yap-api', 'listen_port' => 443, 'rate' => '1.00', 'sort' => 0, 'enabled' => false, 'for_low_priority' => false])
        ->assertHasActionErrors(['inbound_tag']);
    expect(NodeRoute::where('inbound_tag', 'yap-api')->exists())->toBeFalse();
});

test('model rejects the reserved management inbound', function (bool $enabled) {
    expect(fn () => NodeRoute::factory()->create(['inbound_tag' => 'yap-api', 'enabled' => $enabled]))
        ->toThrow(ValidationException::class);
})->with([false, true]);
