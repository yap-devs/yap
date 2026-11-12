<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $tickets = Ticket::where('user_id', $request->user()->id)->latest('updated_at')->paginate(20);

        return Inertia::render('Ticket/Index', compact('tickets'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['subject' => ['required', 'string', 'max:255'], 'body' => ['required', 'string', 'max:10000']]);
        $ticket = DB::transaction(function () use ($request, $data) {
            $ticket = Ticket::create(['user_id' => $request->user()->id, 'subject' => $data['subject'], 'status' => Ticket::STATUS_OPEN]);
            $ticket->messages()->create(['user_id' => $request->user()->id, 'body' => $data['body'], 'is_admin' => false]);

            return $ticket;
        });

        return redirect()->route('tickets.show', $ticket);
    }

    public function show(Request $request, Ticket $ticket)
    {
        abort_if($ticket->user_id !== $request->user()->id, 404);
        $ticket->load('messages');

        return Inertia::render('Ticket/Show', compact('ticket'));
    }

    public function reply(Request $request, Ticket $ticket)
    {
        abort_if($ticket->user_id !== $request->user()->id, 404);
        $data = $request->validate(['body' => ['required', 'string', 'max:10000']]);
        DB::transaction(function () use ($ticket, $request, $data) {
            $ticket = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            abort_if($ticket->status !== Ticket::STATUS_OPEN, 422);
            $ticket->messages()->create(['user_id' => $request->user()->id, 'body' => $data['body'], 'is_admin' => false]);
            $ticket->touch();
        });

        return back();
    }
}
