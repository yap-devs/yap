<?php

use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Str;

test('the existing account adjustment updates the visible balance and ledger', function () {
    config(['services.sub2api.enabled' => false, 'node_agent.enabled' => false]);
    $this->actingAs(User::factory()->create(['id' => 1]));
    $user = User::factory()->create(['name' => 'Workspace customer', 'email' => 'workspace@example.test', 'uuid' => (string) Str::uuid(), 'balance' => '10.00']);
    $path = '/'.trim((string) config('yap.admin_panel_path'), '/').'/users/'.$user->id;

    $page = visit($path.'?relation=ledger')
        ->assertSee('Account overview')
        ->assertSee('Positive balance')
        ->assertSee('No balance details')
        ->click('Adjust balance')
        ->assertSee('Adjust account balance')
        ->fill('input[id$=".amount"]', '2.50')
        ->fill('input[id$=".description"]', 'Browser support correction')
        ->click('Apply adjustment')
        ->assertSee('Balance adjusted')
        ->assertSee('$12.50')
        ->assertSee('Browser support correction')
        ->assertNoJavaScriptErrors();

    $page->screenshot(filename: 'admin-customer-workspace');
    expect($user->fresh()->balance)->toBe('12.50');
    $this->assertDatabaseCount('balance_details', 1);
});

test('mobile order details link to the customer without changing payment state', function () {
    config(['services.sub2api.enabled' => false, 'node_agent.enabled' => false]);
    $this->actingAs(User::factory()->create(['id' => 1]));
    $user = User::factory()->create(['name' => 'Mobile customer', 'uuid' => (string) Str::uuid(), 'balance' => '1.00']);
    $payment = $user->payments()->create(['gateway' => Payment::GATEWAY_ALIPAY, 'status' => Payment::STATUS_EXPIRED, 'amount' => 9]);
    $path = '/'.trim((string) config('yap.admin_panel_path'), '/').'/payments/'.$payment->id;

    visit($path)->on()->mobile()
        ->assertSee('Order details')->assertSee('Expired')->assertDontSee('Compensate payment')
        ->click('Account details')->assertSee('Mobile customer')->assertSee('$1.00')
        ->assertNoJavaScriptErrors();

    expect($payment->fresh()->status)->toBe(Payment::STATUS_EXPIRED);
    $this->assertDatabaseCount('balance_details', 0);
});
