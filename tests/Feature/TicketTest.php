<?php

use App\Filament\Resources\Tickets\Pages\ManageTickets;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Models\Ticket;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

test('users can create and reply to their tickets', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('tickets.store'), ['subject' => 'Connection issue', 'body' => 'Please help'])->assertRedirect();
    $ticket = Ticket::where('user_id', $user->id)->firstOrFail();
    expect($ticket->messages()->count())->toBe(1);
    $this->actingAs($user)->post(route('tickets.reply', $ticket), ['body' => 'More details'])->assertRedirect();
    expect($ticket->messages()->count())->toBe(2);
});

test('other users cannot access or reply to a ticket', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $ticket = Ticket::create(['user_id' => $owner->id, 'subject' => 'Private', 'status' => 'open']);
    $this->actingAs($other)->get(route('tickets.show', $ticket))->assertNotFound();
    $this->actingAs($other)->post(route('tickets.reply', $ticket), ['body' => 'Intrusion'])->assertNotFound();
    expect($ticket->messages()->count())->toBe(0);
});

test('closed tickets reject replies and creation validates input', function () {
    $user = User::factory()->create();
    $ticket = Ticket::create(['user_id' => $user->id, 'subject' => 'Closed', 'status' => 'closed']);
    $this->actingAs($user)->post(route('tickets.reply', $ticket), ['body' => 'Hello'])->assertUnprocessable();
    $this->actingAs($user)->post(route('tickets.store'), ['subject' => '', 'body' => ''])->assertSessionHasErrors(['subject', 'body']);
    expect($ticket->messages()->count())->toBe(0);
});

test('administrator can view reply and close a ticket', function () {
    $admin = User::factory()->create(['id' => 1]);
    $customer = User::factory()->create();
    $ticket = Ticket::create(['user_id' => $customer->id, 'subject' => 'Help', 'status' => 'open']);
    $ticket->messages()->create(['user_id' => $customer->id, 'body' => 'Original message', 'is_admin' => false]);
    $this->actingAs($admin);
    Filament::setCurrentPanel('admin');
    Livewire::test(ManageTickets::class)->assertCanSeeTableRecords([$ticket]);
    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertSee('Original message')
        ->set('reply', 'We can help')
        ->call('sendReply')
        ->assertHasNoErrors()
        ->assertSee('We can help')
        ->callAction('close')
        ->assertHasNoActionErrors();
    expect($ticket->messages()->where('is_admin', true)->where('body', 'We can help')->exists())->toBeTrue();
    expect($ticket->fresh()->status)->toBe('closed');
    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->set('reply', 'Late reply')
        ->call('sendReply')
        ->assertHasErrors(['reply']);
    expect($ticket->messages()->count())->toBe(2);
});

test('non administrators cannot access the ticket workspace', function () {
    User::factory()->create(['id' => 1]);
    $user = User::factory()->create();
    $ticket = Ticket::create(['user_id' => $user->id, 'subject' => 'Private', 'status' => 'open']);
    $this->actingAs($user);
    Filament::setCurrentPanel('admin');
    Livewire::test(ViewTicket::class, ['record' => $ticket->id])->assertForbidden();
});

test('an administrator can reopen a closed ticket and both sides can reply', function () {
    $admin = User::factory()->create(['id' => 1]);
    $customer = User::factory()->create();
    $ticket = Ticket::create(['user_id' => $customer->id, 'subject' => 'Follow up', 'status' => 'closed']);
    $ticket->messages()->create(['user_id' => $customer->id, 'body' => 'Original question', 'is_admin' => false]);
    $this->actingAs($admin);
    Filament::setCurrentPanel('admin');

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertActionVisible('reopen')
        ->assertActionHidden('close')
        ->callAction('reopen')
        ->assertHasNoActionErrors()
        ->assertActionHidden('reopen')
        ->assertActionVisible('close')
        ->assertSee('Original question')
        ->set('reply', 'We are looking into it')
        ->call('sendReply')
        ->assertHasNoErrors();

    expect($ticket->fresh()->status)->toBe(Ticket::STATUS_OPEN);
    $this->actingAs($customer)->post(route('tickets.reply', $ticket), ['body' => 'Thank you'])->assertRedirect();
    expect($ticket->messages()->count())->toBe(3);
});
