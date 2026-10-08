<?php

use App\Filament\Resources\Nodes\Pages\ManageNodes;
use App\Filament\Resources\Nodes\Pages\ViewNode;
use App\Models\Node;
use App\Models\NodeRoute;
use App\Models\User;
use App\Services\NodeHealthService;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    config(['node_agent.enabled' => true, 'node_agent.snapshot_store' => 'array']);
    $this->actingAs(User::factory()->create(['id' => 1]));
    Filament::setCurrentPanel('admin');
    $this->travelTo(now()->startOfSecond());
});

test('node health distinguishes disabled unseen stale pending and synchronized nodes', function () {
    $states = [
        'disabled' => ['enabled' => false],
        'unseen' => ['enabled' => true, 'last_seen_at' => null],
        'offline' => ['enabled' => true, 'last_seen_at' => now()->subMinutes(10)],
        'pending' => ['enabled' => true, 'last_seen_at' => now(), 'desired_revision' => 4, 'applied_revision' => 3],
        'current' => ['enabled' => true, 'last_seen_at' => now(), 'desired_revision' => 4, 'applied_revision' => 4],
    ];
    $nodes = [];
    foreach ($states as $state => $attributes) {
        $nodes[$state] = Node::withoutEvents(fn (): Node => Node::factory()->create($attributes));
        expect(app(NodeHealthService::class)->state($nodes[$state]))->toBe($state);
    }
    Livewire::test(ManageNodes::class)->filterTable('health', 'pending')
        ->assertCanSeeTableRecords([$nodes['pending']])->assertCanNotSeeTableRecords([$nodes['current'], $nodes['offline']]);
    Livewire::test(ViewNode::class, ['record' => $nodes['current']->id])->assertSee('Configuration current')->assertDontSee($nodes['current']->agent_token_hash);
});

test('node details show delivery status without exposing tokens or adding reissue actions', function () {
    $node = Node::factory()->create(['enabled' => true]);

    Livewire::test(ViewNode::class, ['record' => $node->id])
        ->assertOk()->assertSee('Configuration delivery')->assertDontSee($node->agent_token_hash)
        ->assertActionDoesNotExist('resync');
});

test('node detail edits and subsequent requests retain aggregate values and fresh configuration', function () {
    $node = Node::factory()->create(['name' => 'Original name', 'enabled' => true]);
    NodeRoute::factory()->for($node)->create();
    $page = Livewire::test(ViewNode::class, ['record' => $node->id]);
    $page->callAction('edit', ['name' => 'Updated name'])->assertHasNoActionErrors()->assertSee('Updated name');
    expect($page->instance()->record->routes_count)->toBe(1);
    expect($page->instance()->record->desired_revision)->toBe($node->fresh()->desired_revision);
    expect($page->instance()->record->routes_count)->toBe(1);
    $page->call('$refresh');
    expect($page->instance()->record->routes_count)->toBe(1);
});
