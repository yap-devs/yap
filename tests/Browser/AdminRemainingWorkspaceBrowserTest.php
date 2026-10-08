<?php

use App\Models\Node;
use App\Models\NodeRoute;
use App\Models\Package;
use App\Models\User;
use App\Services\Affiliate\AffiliateService;

test('existing subscriptions are visible on the account and subscription list', function () {
    config(['services.sub2api.enabled' => false, 'node_agent.enabled' => false]);
    $this->actingAs(User::factory()->create(['id' => 1]));
    $user = User::factory()->create(['name' => 'Package customer']);
    $product = Package::factory()->create(['name' => 'Browser plan']);
    $user->packages()->create(['package_id' => $product->id, 'status' => 'active', 'remaining_traffic' => $product->traffic_limit, 'started_at' => now(), 'ended_at' => now()->addDays(30)]);
    $path = '/'.trim((string) config('yap.admin_panel_path'), '/');

    visit($path.'/users/'.$user->id.'?relation=packages')
        ->assertSee('Browser plan')->assertDontSee('Grant package')
        ->navigate($path.'/package-subscriptions')->assertSee('Browser plan')
        ->assertNoJavaScriptErrors();
});

test('node settings refresh the same mobile detail page', function () {
    config(['node_agent.enabled' => true, 'node_agent.snapshot_store' => 'array', 'services.sub2api.enabled' => false]);
    $this->actingAs(User::factory()->create(['id' => 1]));
    $node = Node::factory()->create(['name' => 'Browser node', 'enabled' => true, 'last_seen_at' => now()]);
    NodeRoute::factory()->for($node)->create();
    $path = '/'.trim((string) config('yap.admin_panel_path'), '/');

    visit($path.'/nodes/'.$node->id)->on()->mobile()
        ->assertSee('Node health')
        ->click('Edit settings')
        ->fill('input[type="text"][id$=".name"]', 'Renamed browser node')
        ->click('Save changes')
        ->assertSee('Renamed browser node')
        ->assertDontSee('Reissue configuration')
        ->assertNoJavaScriptErrors();
    expect($node->fresh()->name)->toBe('Renamed browser node');
});

test('promoter configuration and expanded report pages work in the browser', function () {
    config(['services.sub2api.enabled' => false, 'node_agent.enabled' => false]);
    $this->actingAs(User::factory()->create(['id' => 1]));
    $owner = User::factory()->create();
    $promoter = app(AffiliateService::class)->ensurePromoter($owner);
    $path = '/'.trim((string) config('yap.admin_panel_path'), '/');

    visit($path.'/affiliate-promoters/'.$promoter->id)
        ->assertSee('Eligibility and earnings')
        ->click('Configure promoter')
        ->fill('input[id$=".rate_percent"]', '25')
        ->click('Submit')
        ->assertSee('Promoter settings updated')
        ->assertSee('25.00%')
        ->navigate($path.'/affiliate-overview')
        ->assertSee('Due for processing')
        ->navigate($path.'/ai-analytics')
        ->assertSee('Monthly Analysis Window')
        ->assertSee('Recent AI Requests')
        ->assertNoJavaScriptErrors();
    expect($promoter->fresh()->custom_commission_rate)->toBe('0.2500');
});
