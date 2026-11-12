<?php

use App\Models\Ticket;
use App\Models\User;

test('customer can find a ticket and reply from the inbox', function () {
    $customer = User::factory()->create();
    $this->actingAs($customer);
    $ticket = Ticket::create(['user_id' => $customer->id, 'subject' => 'Connection help', 'status' => 'open']);
    $ticket->messages()->create(['user_id' => $customer->id, 'body' => 'My first message', 'is_admin' => false]);

    visit('/tickets')
        ->assertSee('Connection help')
        ->click('Connection help')
        ->assertSee('My first message')
        ->fill('#ticket-reply', 'More information')
        ->click('Send reply')
        ->assertSee('More information')
        ->assertNoJavaScriptErrors();

    expect($ticket->messages()->where('body', 'More information')->exists())->toBeTrue();
});

test('customer can open the ticket form on mobile', function () {
    $customer = User::factory()->create();
    $this->actingAs($customer);

    visit('/tickets')->on()->mobile()
        ->click('New ticket')
        ->fill('#ticket-subject', 'Mobile question')
        ->fill('#ticket-body', 'Please help')
        ->click('Submit ticket')
        ->assertSee('Mobile question')
        ->assertSee('Please help')
        ->assertNoJavaScriptErrors();
});
