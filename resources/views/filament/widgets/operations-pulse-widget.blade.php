@php
    $pollingInterval = $this->getPollingInterval();
@endphp

<x-filament-widgets::widget
    :attributes="
        (new \Illuminate\View\ComponentAttributeBag)
            ->merge([
                'wire:poll.' . $pollingInterval => $pollingInterval ? true : null,
            ], escape: false)
    "
>
    <div class="grid gap-4 xl:grid-cols-12">
        <section class="xl:col-span-7 rounded-3xl bg-gradient-to-br from-slate-950 via-slate-900 to-blue-950 p-6 text-white shadow-sm ring-1 ring-white/10">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="space-y-3">
                    <div class="inline-flex items-center rounded-full bg-white/10 px-3 py-1 text-xs font-medium uppercase tracking-[0.18em] text-slate-200">
                        Operations Pulse
                    </div>

                    <div>
                        <h2 class="text-2xl font-semibold tracking-tight">
                            {{ $monthLabel }} business flow
                        </h2>

                        <p class="mt-2 max-w-2xl text-sm text-slate-300">
                            Cash-in, actual usage, and access risk are presented together so you can tell whether growth is healthy or only deferred in user balances.
                        </p>
                    </div>
                </div>

                <div class="grid gap-2 text-sm text-slate-200 sm:grid-cols-2 lg:grid-cols-1">
                    <div class="rounded-2xl bg-white/5 px-4 py-3 ring-1 ring-white/10">
                        <div class="text-xs uppercase tracking-wide text-slate-400">Trend Window</div>
                        <div class="mt-1 font-medium text-white">{{ $trendWindowLabel }}</div>
                    </div>
                    <div class="rounded-2xl bg-white/5 px-4 py-3 ring-1 ring-white/10">
                        <div class="text-xs uppercase tracking-wide text-slate-400">Refresh Mode</div>
                        <div class="mt-1 font-medium text-white">{{ $pollingLabel }}</div>
                    </div>
                </div>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-3">
                <div class="rounded-2xl bg-emerald-400/10 p-4 ring-1 ring-emerald-300/20">
                    <div class="text-xs uppercase tracking-wide text-emerald-200/80">Current Month Top-Ups</div>
                    <div class="mt-2 text-2xl font-semibold text-white">{{ $currentMonthTopUp }}</div>
                </div>
                <div class="rounded-2xl bg-rose-400/10 p-4 ring-1 ring-rose-300/20">
                    <div class="text-xs uppercase tracking-wide text-rose-200/80">Current Month Usage</div>
                    <div class="mt-2 text-2xl font-semibold text-white">{{ $currentMonthUsage }}</div>
                </div>
                <div class="rounded-2xl bg-amber-400/10 p-4 ring-1 ring-amber-300/20">
                    <div class="text-xs uppercase tracking-wide text-amber-200/80">Monthly Delta</div>
                    <div class="mt-2 text-2xl font-semibold text-white">{{ $monthlyDelta }}</div>
                    <div class="mt-2 text-xs {{ $monthlyDeltaTone === 'success' ? 'text-emerald-200' : 'text-rose-200' }}">
                        {{ $monthlyDeltaLabel }}
                    </div>
                </div>
            </div>
        </section>

        <section class="xl:col-span-5 grid gap-4 sm:grid-cols-2">
            <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Outstanding Balance</div>
                <div class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ $outstandingBalance }}</div>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Cash still sitting in user balances and not yet recognized as usage.</p>
            </div>

            <div class="rounded-3xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Package-backed Users</div>
                <div class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ $packageBackedUsers }}</div>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Users whose access is currently protected by active packages.</p>
            </div>

            <div class="rounded-3xl bg-rose-50 p-5 shadow-sm ring-1 ring-rose-200 dark:bg-rose-950/30 dark:ring-rose-800/60">
                <div class="text-xs uppercase tracking-wide text-rose-500 dark:text-rose-300">At-risk Users</div>
                <div class="mt-2 text-2xl font-semibold text-rose-700 dark:text-rose-200">{{ $atRiskUsers }}</div>
                <p class="mt-2 text-sm text-rose-600 dark:text-rose-300">Users likely to churn or lose access soon without another top-up or package.</p>
            </div>

            <div class="rounded-3xl bg-blue-50 p-5 shadow-sm ring-1 ring-blue-200 dark:bg-blue-950/30 dark:ring-blue-800/60">
                <div class="text-xs uppercase tracking-wide text-blue-500 dark:text-blue-300">Package Capacity</div>
                <div class="mt-2 text-2xl font-semibold text-blue-700 dark:text-blue-200">{{ $remainingTraffic }}</div>
                <p class="mt-2 text-sm text-blue-600 dark:text-blue-300">Across {{ $activePackages }} active packages, this is the traffic inventory still available.</p>
            </div>
        </section>
    </div>
</x-filament-widgets::widget>
