<x-filament-panels::page>
    @php
        $ticket = $this->getRecord();
        $customer = $ticket->user;
        $messages = $this->getConversationMessages();
    @endphp

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_19rem] xl:items-start">
        <section class="min-w-0 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-6 py-5 dark:border-white/10">
                <div>
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Conversation</h2>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $messages->count() }} {{ \Illuminate\Support\Str::plural('message', $messages->count()) }} · Visible to the customer</p>
                </div>
                <span class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-medium {{ $ticket->status === 'open' ? 'bg-green-50 text-green-700 dark:bg-green-400/10 dark:text-green-400' : 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300' }}">
                    <span class="h-1.5 w-1.5 rounded-full {{ $ticket->status === 'open' ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                    {{ $ticket->status === 'open' ? 'Open' : 'Closed' }}
                </span>
            </div>

            <div class="space-y-6 bg-gray-50/70 px-5 py-6 sm:px-6 dark:bg-gray-950/30">
                @forelse ($messages as $message)
                    <article class="flex items-start gap-3" wire:key="ticket-message-{{ $message->id }}">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $message->is_admin ? 'bg-amber-100 text-amber-800 dark:bg-amber-400/10 dark:text-amber-400' : 'bg-blue-100 text-blue-700 dark:bg-blue-400/10 dark:text-blue-400' }}">
                            {{ $message->is_admin ? 'S' : mb_strtoupper(mb_substr($customer?->name ?? 'C', 0, 1)) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="mb-2 flex flex-wrap items-baseline gap-x-2 gap-y-1">
                                <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $message->is_admin ? 'Support' : ($customer?->name ?? 'Customer') }}</span>
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ $message->is_admin ? 'Support reply' : 'Customer' }} · <time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->format('Y-m-d H:i') }}</time></span>
                            </div>
                            <div class="whitespace-pre-wrap break-words rounded-xl rounded-tl-sm bg-white px-4 py-3 text-sm leading-6 text-gray-700 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:text-gray-200 dark:ring-white/10">{{ $message->body }}</div>
                        </div>
                    </article>
                @empty
                    <p class="py-8 text-center text-sm text-gray-500">No messages yet.</p>
                @endforelse
            </div>

            <div class="border-t border-gray-100 p-5 sm:p-6 dark:border-white/10">
                @if ($ticket->status === 'open')
                    <form wire:submit="sendReply" class="space-y-3">
                        <label for="ticket-reply" class="block text-sm font-semibold text-gray-950 dark:text-white">Reply to customer</label>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Your reply will be visible to the customer.</p>
                        <textarea id="ticket-reply" wire:model="reply" rows="5" maxlength="10000" required placeholder="Write your reply…" class="block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/20 dark:bg-gray-950 dark:text-white"></textarea>
                        @error('reply') <p class="text-sm text-danger-600" role="alert">{{ $message }}</p> @enderror
                        <div class="flex justify-end"><x-filament::button type="submit" icon="heroicon-m-paper-airplane" wire:loading.attr="disabled" wire:target="sendReply">Send reply</x-filament::button></div>
                    </form>
                @else
                    <div class="rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-600 dark:bg-white/5 dark:text-gray-300">This ticket is closed. Replies are disabled.</div>
                @endif
            </div>
        </section>

        <aside class="space-y-5">
            <section class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Customer</h2>
                <p class="mt-4 break-words text-sm font-medium text-gray-900 dark:text-white">{{ $customer?->name ?? 'Unavailable' }}</p>
                @if ($customer)
                    <p class="mt-1 break-all text-sm text-gray-500 dark:text-gray-400">{{ $customer->email }}</p>
                    <a class="mt-4 inline-flex text-sm font-medium text-primary-600 hover:underline dark:text-primary-400" href="{{ \App\Filament\Resources\UserResource::getUrl('view', ['record' => $customer]) }}">View customer profile →</a>
                @endif
            </section>
            <section class="rounded-xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Ticket details</h2>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-gray-500 dark:text-gray-400">Ticket</dt><dd class="font-medium text-gray-900 dark:text-white">#{{ $ticket->id }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500 dark:text-gray-400">Created</dt><dd class="text-right text-gray-900 dark:text-white">{{ $ticket->created_at->format('Y-m-d H:i') }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-gray-500 dark:text-gray-400">Last activity</dt><dd class="text-right text-gray-900 dark:text-white">{{ $ticket->updated_at->format('Y-m-d H:i') }}</dd></div>
                </dl>
            </section>
        </aside>
    </div>
</x-filament-panels::page>
