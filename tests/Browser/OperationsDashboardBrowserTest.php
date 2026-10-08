<?php

use App\Models\Node;
use App\Models\User;

test('operations dashboards render and navigate in a browser', function () {
    $this->actingAs(User::factory()->create(['id' => 1]));
    $path = '/'.trim((string) config('yap.admin_panel_path'), '/');

    $page = visit($path)
        ->assertSee('Operations Overview')
        ->assertSee('Paid Top-Ups Today')
        ->assertDontSee('Find an account')
        ->assertSee('Needs attention')
        ->assertSee('Automatic processing');
    $page->script('window.scrollTo(0, document.body.scrollHeight)');
    $page->assertSee('Monthly Top-Ups & Balance Debits')
        ->assertDontSee('Last Scheduler Run')
        ->click('summary:has-text("Scheduled jobs")')
        ->assertSee('Last Scheduler Run')
        ->assertSee('Next Expected Cron Tick')
        ->assertSee('Next Scheduled Task')
        ->assertDontSee('Local Backup Status')
        ->click('summary:has-text("Local database backup")')
        ->assertSee('Local Backup Status')
        ->assertSee('Next Expected Backup')
        ->assertSee('Latest Backup Size')
        ->click('Refresh now')
        ->assertSee('Last Scheduler Run')
        ->assertSee('Local Backup Status')
        ->assertNoJavaScriptErrors()
        ->navigate($path.'/cash-flow')
        ->assertSee('Cash Flow & Balance Debits')
        ->assertSee('Monthly Top-Ups & Balance Debits')
        ->assertNoJavaScriptErrors()
        ->navigate($path.'/traffic-reports')
        ->assertSee('Traffic Trends')
        ->assertSee('Monthly Traffic Report')
        ->assertNoJavaScriptErrors()
        ->navigate($path.'/access-health')
        ->assertSee('Access Health')
        ->assertSee('User Activity')
        ->assertNoJavaScriptErrors();
});

test('operations overview renders on a mobile viewport', function () {
    $this->actingAs(User::factory()->create(['id' => 1]));

    $page = visit('/'.trim((string) config('yap.admin_panel_path'), '/'))
        ->on()->mobile()
        ->assertSee('Operations Overview')
        ->assertSee('Paid Top-Ups Today')
        ->assertDontSee('Find an account')
        ->assertSee('Common operations')
        ->assertSee('Needs attention')
        ->assertDontSee('Last Scheduler Run')
        ->assertDontSee('Next Expected Backup')
        ->assertNoJavaScriptErrors();
    $page->screenshot(filename: 'operations-overview-mobile');
    $page->click('.fi-topbar-open-sidebar-btn')->assertSee('Customers')->assertSee('Affiliates');
});

test('overview status tiles open the corresponding filtered node list', function () {
    config(['node_agent.enabled' => true, 'node_agent.snapshot_store' => 'array']);
    $this->actingAs(User::factory()->create(['id' => 1]));
    $node = Node::withoutEvents(fn (): Node => Node::factory()->create(['name' => 'Stale overview node', 'enabled' => true, 'last_seen_at' => now()->subMinutes(10)]));
    $path = '/'.trim((string) config('yap.admin_panel_path'), '/');
    $page = visit($path)->assertSee('Operations status')->assertDontSee('Find an account');
    $page->screenshot(filename: 'operations-overview-desktop');
    $page->click('a:has-text("Nodes with stale heartbeats")')
        ->assertSee($node->name)->assertNoJavaScriptErrors();
});
