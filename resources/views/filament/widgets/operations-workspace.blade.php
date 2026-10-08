<x-filament-widgets::widget>
    <div class="space-y-6" @if ($polling_interval) wire:poll.{{ $polling_interval }}="refreshDashboardWidget" @endif>
        <section aria-label="Today at a glance" class="overflow-hidden rounded-xl bg-white ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-950/5 px-5 py-3 dark:border-white/10">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Today at a glance</h2>
                <span class="text-xs text-gray-500 dark:text-gray-400">Checked {{ $checked_at }}</span>
            </div>
            <dl class="grid grid-cols-2 gap-px bg-gray-950/5 lg:grid-cols-4 dark:bg-white/10">
                @foreach ([['label' => 'Paid Top-Ups Today', 'value' => '$'.number_format($today['top_up'], 2)], ['label' => 'Balance Debits Today', 'value' => '$'.number_format($today['usage'], 2)], ['label' => 'Traffic Collected Today', 'value' => number_format($today['traffic_gb'], 2).' GiB'], ['label' => 'Users with Traffic Today', 'value' => number_format($today['active_users'])]] as $metric)
                    <div class="min-w-0 bg-white px-5 py-4 dark:bg-gray-900">
                        <dt class="text-xs text-gray-500 dark:text-gray-400">{{ $metric['label'] }}</dt>
                        <dd class="mt-1 text-2xl font-semibold tracking-tight text-gray-950 tabular-nums dark:text-white">{{ $metric['value'] }}</dd>
                    </div>
                @endforeach
        </dl>
        </section>

        @livewire(\App\Filament\Widgets\MonthlyTopUpAndUsageChart::class, ['pageFilters' => $this->pageFilters], key('overview-monthly-cash-flow'))

        <x-filament::section>
            <x-slot name="heading">Operations status</x-slot>
            <x-slot name="description">{{ $attention_checks ? 'Checks to follow up: '.$attention_checks : 'No customer or node alerts in these checks' }}</x-slot>
            <div class="grid grid-cols-2 items-start gap-3 lg:grid-cols-3">
                @foreach ($attention as $item)
                    <a href="{{ $item['url'] }}" title="{{ $item['description'] }}" class="group min-w-0 self-stretch rounded-lg border border-gray-950/10 p-3 transition hover:border-primary-400 dark:border-white/10 dark:hover:border-primary-500">
                        <span class="block text-xs text-gray-500 dark:text-gray-400">Needs attention</span>
                        <div class="mt-2 flex items-start justify-between gap-2">
                            <span class="text-sm font-medium text-gray-950 group-hover:text-primary-600 dark:text-white dark:group-hover:text-primary-400">{{ $item['label'] }}</span>
                            <x-filament::icon :icon="$item['icon']" class="h-4 w-4 shrink-0 text-gray-400" />
                        </div>
                        <span @class(['mt-2 block text-2xl font-semibold tabular-nums', 'text-amber-600 dark:text-amber-400' => $item['count'] > 0, 'text-gray-400 dark:text-gray-500' => $item['count'] === 0])>{{ number_format($item['count']) }}</span>
                    </a>
                @endforeach
                @foreach ($waiting as $item)
                    <a href="{{ $item['url'] }}" title="{{ $item['description'] }}" class="group min-w-0 self-stretch rounded-lg border border-gray-950/10 p-3 transition hover:border-primary-400 dark:border-white/10 dark:hover:border-primary-500">
                        <span class="block text-xs text-gray-500 dark:text-gray-400">Automatic processing</span>
                        <div class="mt-2 flex items-start justify-between gap-2">
                            <span class="text-sm font-medium text-gray-950 group-hover:text-primary-600 dark:text-white dark:group-hover:text-primary-400">{{ $item['label'] }}</span>
                            <x-filament::icon icon="heroicon-o-arrow-path" class="h-4 w-4 shrink-0 text-gray-400" />
                        </div>
                        <span class="mt-2 block text-2xl font-semibold text-gray-950 tabular-nums dark:text-white">{{ number_format($item['count']) }}</span>
                    </a>
                @endforeach
                <details wire:ignore.self wire:key="overview-scheduler-details" class="group min-w-0 self-stretch rounded-lg border border-gray-950/10 p-3 open:col-span-full dark:border-white/10">
                    <summary class="cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                        <span class="block text-xs text-gray-500 dark:text-gray-400">System health</span>
                        <span class="mt-2 flex items-start justify-between gap-2">
                            <span class="text-sm font-medium text-gray-950 dark:text-white">Scheduled jobs</span>
                            <x-filament::icon icon="heroicon-m-chevron-down" class="h-4 w-4 shrink-0 text-gray-400 transition group-open:rotate-180" />
                        </span>
                        <span @class(['mt-2 block text-sm font-medium', 'text-green-600 dark:text-green-400' => $scheduler_healthy, 'text-amber-600 dark:text-amber-400' => ! $scheduler_healthy])>{{ $scheduler_label }}</span>
                    </summary>
                    <dl class="mt-4 grid grid-cols-1 gap-3 rounded-lg bg-gray-50 p-4 text-xs sm:grid-cols-2 dark:bg-gray-800/50">
                        <div><dt class="text-gray-500 dark:text-gray-400">Last Scheduler Run</dt><dd class="mt-1 text-gray-950 dark:text-white">{{ $this->formatDate($scheduler['scheduler']['started_at'] ?? null) }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Last Scheduler Completion</dt><dd class="mt-1 text-gray-950 dark:text-white">{{ $this->formatDate($scheduler['scheduler']['finished_at'] ?? null) }} · {{ $scheduler['scheduler']['status'] ?? 'Unknown' }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Next Expected Cron Tick</dt><dd class="mt-1 text-gray-950 dark:text-white">{{ $this->formatDate($scheduler['next_tick']) }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Next Scheduled Task</dt><dd class="mt-1 break-words text-gray-950 dark:text-white">{{ $next_task['name'] ?? 'None' }} · {{ $this->formatDate($next_task['next_at'] ?? null) }}</dd></div>
                        @foreach ($scheduler['tasks'] as $task)
                            @if (($task['state']['status'] ?? null) === 'failed')
                                <div class="text-danger-600 sm:col-span-2 dark:text-danger-400">Failed: {{ $task['name'] }}</div>
                            @endif
                        @endforeach
                        <p class="text-gray-500 sm:col-span-2 dark:text-gray-400">Expected times follow the schedule; host delivery is not guaranteed.</p>
                    </dl>
                </details>
                <details wire:ignore.self wire:key="overview-backup-details" class="group min-w-0 self-stretch rounded-lg border border-gray-950/10 p-3 open:col-span-full dark:border-white/10">
                    <summary class="cursor-pointer list-none [&::-webkit-details-marker]:hidden">
                        <span class="block text-xs text-gray-500 dark:text-gray-400">System health</span>
                        <span class="mt-2 flex items-start justify-between gap-2">
                            <span class="text-sm font-medium text-gray-950 dark:text-white">Local database backup</span>
                            <x-filament::icon icon="heroicon-m-chevron-down" class="h-4 w-4 shrink-0 text-gray-400 transition group-open:rotate-180" />
                        </span>
                        <span @class(['mt-2 block text-sm font-medium', 'text-green-600 dark:text-green-400' => $backup['status'] === 'healthy', 'text-gray-500 dark:text-gray-400' => $backup['status'] === 'disabled', 'text-amber-600 dark:text-amber-400' => ! in_array($backup['status'], ['healthy', 'disabled'], true)])>{{ $backup_label }}</span>
                    </summary>
                    <dl class="mt-4 grid grid-cols-1 gap-3 rounded-lg bg-gray-50 p-4 text-xs sm:grid-cols-2 dark:bg-gray-800/50">
                        <div><dt class="text-gray-500 dark:text-gray-400">Local Backup Status</dt><dd class="mt-1 text-gray-950 dark:text-white">{{ $backup_label }} · Every {{ $backup['interval_hours'] }} hour(s)</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Latest Backup</dt><dd class="mt-1 text-gray-950 dark:text-white">{{ $this->formatDate($latest_backup_at) }}<span class="mt-1 block text-gray-500 dark:text-gray-400">{{ $backup['completed_at'] ? 'Actual completion time' : 'File timestamp; completion not recorded' }}</span></dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Latest Backup Size</dt><dd class="mt-1 text-gray-950 dark:text-white">{{ $latest_backup_size }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Retained Backups</dt><dd class="mt-1 text-gray-950 dark:text-white">{{ number_format($backup['count']) }} · {{ $total_backup_size }} · Retain {{ $backup['retention_hours'] }} hours</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Next Expected Backup</dt><dd class="mt-1 text-gray-950 dark:text-white">{{ $backup['enabled'] ? $this->formatDate($backup['next_expected_at']) : 'Disabled' }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Last Backup Attempt</dt><dd class="mt-1 text-gray-950 dark:text-white">{{ $backup['last_attempt']['status'] ?? 'Not recorded' }} · {{ $this->formatDate($backup['last_attempt']['finished_at'] ?? $backup['last_attempt']['started_at'] ?? null) }}</dd></div>
                        <div><dt class="text-gray-500 dark:text-gray-400">Gzip verification</dt><dd class="mt-1 text-gray-950 dark:text-white">{{ $this->formatDate($backup['gzip_verified_at']) }}</dd></div>
                        <p class="text-gray-500 sm:col-span-2 dark:text-gray-400">File availability does not verify restore readiness. Completion and gzip checks show recorded evidence only.</p>
                    </dl>
                </details>
            </div>
            <p class="mt-4 text-xs text-gray-400 dark:text-gray-500">Customer and package checks exclude internal accounts #1–5. Payment and commission queues include all accounts and run automatically.</p>
        </x-filament::section>

        <x-filament::section>
                <x-slot name="heading">Common operations</x-slot>
                <nav aria-label="Common operations" class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                    @foreach ($quick_links as $link)
                        <a href="{{ $link['url'] }}" title="{{ $link['description'] }}" class="group flex min-w-0 items-center gap-2 rounded-lg border border-gray-950/10 p-3 text-sm font-medium text-gray-950 hover:border-primary-400 hover:text-primary-600 dark:border-white/10 dark:text-white dark:hover:border-primary-500 dark:hover:text-primary-400">
                            <x-filament::icon :icon="$link['icon']" class="h-4 w-4 shrink-0 text-gray-400" />
                            <span>{{ $link['label'] }}</span>
                        </a>
                    @endforeach
                </nav>
        </x-filament::section>

        <div class="flex flex-wrap items-center gap-x-6 gap-y-3 rounded-xl border border-dashed border-gray-300 px-5 py-4 dark:border-gray-700">
            <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">Explore reports</span>
            @foreach ($reports as $report)
                <a href="{{ $report['url'] }}" class="text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">{{ $report['label'] }} &rarr;</a>
            @endforeach
        </div>
        <p class="text-xs text-gray-400 dark:text-gray-500">Today’s business figures exclude internal accounts #1–5. Paid orders use their creation date; traffic follows the latest collection.</p>
    </div>
</x-filament-widgets::widget>
