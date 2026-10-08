<?php

use App\Filament\Widgets\OperationsWorkspace;
use App\Models\Node;
use App\Models\Payment;
use App\Models\User;
use App\Services\AdminOperationsOverviewService;
use Filament\Facades\Filament;
use Livewire\Livewire;

test('the operations workspace separates access issues from automatic queues and links to their records', function () {
    config(['node_agent.enabled' => true, 'backup.enabled' => false]);
    $this->actingAs(User::factory()->create(['id' => 1]));
    Filament::setCurrentPanel('admin');
    User::factory()->create(['id' => 5, 'balance' => '-20.00']);
    $customer = User::factory()->create(['id' => 6, 'balance' => '0.50']);
    $customer->payments()->create(['amount' => 5, 'gateway' => 'alipay', 'status' => Payment::STATUS_CREATED]);
    Node::withoutEvents(fn () => Node::factory()->create(['enabled' => true, 'last_seen_at' => now()->subMinutes(10)]));
    Node::withoutEvents(fn () => Node::factory()->create(['enabled' => false, 'last_seen_at' => now()->subMinutes(10)]));

    $overview = app(AdminOperationsOverviewService::class)->overview();
    $attention = collect($overview['attention'])->keyBy('key');
    $waiting = collect($overview['waiting'])->keyBy('key');
    expect($attention['low_balance']['count'])->toBe(1);
    expect($attention['offline']['count'])->toBe(1);
    expect($attention['offline']['url'])->toContain('health');
    expect($waiting['orders']['count'])->toBe(1);

    Livewire::test(OperationsWorkspace::class, ['pageFilters' => ['polling_interval' => 'off']])
        ->assertDontSee('Find an account')->assertSee('Operations status')->assertSee('Needs attention')->assertSee('Automatic processing')
        ->assertSee('System health')->assertDontSeeHtml('wire:poll');
});

test('overview retains the monthly chart and omits withdrawn search and audit features', function () {
    config(['node_agent.enabled' => false]);
    $this->actingAs(User::factory()->create(['id' => 1]));
    Filament::setCurrentPanel('admin');

    Livewire::test(OperationsWorkspace::class)
        ->assertSee('Monthly Top-Ups & Balance Debits')
        ->assertSee('Common operations')
        ->assertDontSee('Find an account')
        ->assertDontSee('Management history');
});

test('workspace actions remain restricted to the administrator', function () {
    $this->actingAs(User::factory()->create(['id' => 6]));
    Livewire::test(OperationsWorkspace::class)->assertForbidden();
});
