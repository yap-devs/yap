<?php

use App\Models\Node;
use App\Models\Ticket;
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

test('monthly cash flow chart fits a mobile viewport', function () {
    $this->actingAs(User::factory()->create(['id' => 1]));
    $path = '/'.trim((string) config('yap.admin_panel_path'), '/').'/cash-flow';
    $selector = 'canvas[aria-label^="Monthly Top-Ups & Balance Debits"]';

    $page = visit($path)->on()->mobile()
        ->assertVisible($selector)
        ->assertNoJavaScriptErrors();

    $page->screenshotElement('.fi-wi-chart:has('.$selector.')', filename: 'monthly-cash-flow-mobile');
    $dimensions = $page->script(<<<'JS'
        (() => {
            const canvas = document.querySelector('canvas[aria-label^="Monthly Top-Ups & Balance Debits"]');
            const rect = canvas.getBoundingClientRect();
            return { width: rect.width, height: rect.height, left: rect.left, right: rect.right, viewport: document.documentElement.clientWidth, scrollWidth: document.documentElement.scrollWidth };
        })()
        JS);

    expect($dimensions['width'])->toBeGreaterThan(200)
        ->and($dimensions['height'])->toBeGreaterThan(200)->toBeLessThan(400)
        ->and($dimensions['left'])->toBeGreaterThanOrEqual(0)
        ->and($dimensions['right'])->toBeLessThanOrEqual($dimensions['viewport'])
        ->and($dimensions['scrollWidth'])->toBeLessThanOrEqual($dimensions['viewport']);

    $desktop = visit($path)->on()->desktop()
        ->assertVisible($selector)
        ->assertNoJavaScriptErrors();
    $desktop->screenshotElement('.fi-wi-chart:has('.$selector.')', filename: 'monthly-cash-flow-desktop');
    $desktop_height = $desktop->script('document.querySelector(\'canvas[aria-label^="Monthly Top-Ups & Balance Debits"]\').getBoundingClientRect().height');

    expect($desktop_height)->toBeGreaterThan(200)->toBeLessThanOrEqual(320);
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

test('ticket alert opens the waiting for reply queue', function () {
    $this->actingAs(User::factory()->create(['id' => 1]));
    $customer = User::factory()->create();
    $waiting = Ticket::create(['user_id' => $customer->id, 'subject' => 'Waiting for support', 'status' => 'open']);
    $waiting->messages()->create(['user_id' => $customer->id, 'is_admin' => false, 'body' => 'Help']);
    $answered = Ticket::create(['user_id' => $customer->id, 'subject' => 'Already answered', 'status' => 'open']);
    $answered->messages()->create(['user_id' => 1, 'is_admin' => true, 'body' => 'Answer']);

    visit('/'.trim((string) config('yap.admin_panel_path'), '/'))
        ->assertSee('1 support ticket awaiting reply')
        ->click('a[href*="needs_reply"]')
        ->assertSee('Waiting for support')
        ->assertDontSee('Already answered')
        ->assertNoJavaScriptErrors();
});
