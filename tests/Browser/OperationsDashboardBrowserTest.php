<?php

use App\Models\User;

test('operations dashboards render and navigate in a browser', function () {
    $this->actingAs(User::factory()->create(['id' => 1]));
    $path = '/'.trim((string) config('yap.admin_panel_path'), '/');

    visit($path)
        ->assertSee('Operations Overview')
        ->assertSee('Paid Top-Ups Today')
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

    visit('/'.trim((string) config('yap.admin_panel_path'), '/'))
        ->on()->mobile()
        ->assertSee('Operations Overview')
        ->assertSee('Paid Top-Ups Today')
        ->click('.fi-topbar-open-sidebar-btn')
        ->assertSee('Customers')
        ->assertSee('Affiliates')
        ->assertNoJavaScriptErrors();
});
