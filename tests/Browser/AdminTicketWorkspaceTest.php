<?php

use App\Models\Ticket;
use App\Models\User;

test('administrator reads and replies from the ticket workspace', function () {
    $this->actingAs(User::factory()->create(['id' => 1]));
    $customer = User::factory()->create(['name' => 'Ticket customer']);
    $ticket = Ticket::create(['user_id' => $customer->id, 'subject' => 'Connection issue', 'status' => 'open']);
    $ticket->messages()->create(['user_id' => $customer->id, 'body' => 'Cannot connect', 'is_admin' => false]);

    visit('/'.trim((string) config('yap.admin_panel_path'), '/').'/tickets/'.$ticket->id)
        ->assertSee('Connection issue')
        ->assertSee('Ticket customer')
        ->assertSee('Cannot connect')
        ->fill('#ticket-reply', 'Please try again')
        ->click('Send reply')
        ->assertSee('Please try again')
        ->assertNoJavaScriptErrors();

    expect($ticket->messages()->where('is_admin', true)->where('body', 'Please try again')->exists())->toBeTrue();
});
