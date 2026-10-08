<?php

use App\Jobs\RefreshNodeAuthorization;
use App\Models\Node;
use App\Models\User;
use App\Services\NodeAuthorizationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    config(['node_agent.enabled' => false]);
    $this->node = Node::factory()->create();
    $this->user = User::factory()->create(['balance' => '0.00', 'github_created_at' => null]);
    config(['node_agent.enabled' => true]);
});

test('authorization updates are durable but do not lock nodes before financial commit', function () {
    $revision = $this->node->desired_revision;
    DB::transaction(function () use ($revision): void {
        $user = User::lockForUpdate()->findOrFail($this->user->id);
        $user->update(['balance' => '10.00']);
        expect($this->node->fresh()->desired_revision)->toBe($revision)
            ->and(DB::table('jobs')->where('payload->displayName', RefreshNodeAuthorization::class)->count())->toBe(1);
    });
    expect($this->node->fresh()->desired_revision)->toBeGreaterThan($revision)
        ->and(DB::table('jobs')->count())->toBe(0);
});

test('financial rollback discards its authorization notification', function () {
    $revision = $this->node->desired_revision;
    try {
        DB::transaction(function (): void {
            $this->user->update(['balance' => '10.00']);
            throw new RuntimeException('rollback fixture');
        });
    } catch (RuntimeException) {
    }
    expect($this->node->fresh()->desired_revision)->toBe($revision)
        ->and($this->user->fresh()->balance)->toBe('0.00')
        ->and(DB::table('jobs')->count())->toBe(0);
});

test('failed immediate authorization refresh retains a retry without failing the payment', function () {
    $service = Mockery::mock(NodeAuthorizationService::class)->makePartial();
    $service->shouldReceive('refresh')->once()->andThrow(new RuntimeException('temporary node lock timeout'));
    app()->instance(NodeAuthorizationService::class, $service);
    DB::transaction(fn () => $this->user->update(['balance' => '10.00']));
    expect($this->user->fresh()->balance)->toBe('10.00')
        ->and(DB::table('jobs')->where('payload->displayName', RefreshNodeAuthorization::class)->count())->toBe(1);
    app()->forgetInstance(NodeAuthorizationService::class);
    $revision = $this->node->fresh()->desired_revision;
    $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--tries' => 3])->assertSuccessful();
    expect($this->node->fresh()->desired_revision)->toBeGreaterThan($revision)
        ->and(DB::table('jobs')->count())->toBe(0);
});

test('multiple changes in one transaction retain their captured authorization decisions', function () {
    $revision = $this->node->desired_revision;
    DB::transaction(function (): void {
        $this->user->update(['balance' => '10.00']);
        $this->user->update(['name' => 'Updated name']);
    });
    expect($this->node->fresh()->desired_revision)->toBeGreaterThan($revision)
        ->and(DB::table('jobs')->count())->toBe(0);
});

test('a separate queue connection cannot silently orphan authorization notifications', function () {
    config(['queue.connections.database.connection' => 'separate_queue']);
    expect(fn () => DB::transaction(fn () => $this->user->update(['balance' => '10.00'])))
        ->toThrow(LogicException::class, 'application connection');
    expect($this->user->fresh()->balance)->toBe('0.00')
        ->and(DB::table('jobs')->count())->toBe(0);
});
