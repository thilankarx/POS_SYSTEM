<div>
    @section('title', 'Shift history')

    @php
        $formatMoney = static fn ($money) => \App\Support\Money\Money::format(\App\Support\Money\Money::of($money));
        $formatDuration = static function ($shift) {
            $minutes = (int) $shift->opened_at->diffInMinutes($shift->closed_at ?? now());
            $hours = intdiv($minutes, 60);
            $remaining = $minutes % 60;

            return $hours > 0 ? "{$hours}h {$remaining}m" : "{$remaining}m";
        };
        $hasFilters = $search !== '' || $status !== '' || $terminal !== '' || $period !== '' || $sort !== 'newest';
    @endphp

    <div class="space-y-6">
        <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-950 dark:text-white">Shift history</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Review register activity and drawer reconciliation.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('shift.manage') }}" class="inline-flex h-10 items-center gap-2 rounded-md border border-slate-300 bg-white px-3.5 text-sm font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
                    Current shift
                </a>
                @can('reports.shifts')
                    <a href="{{ route('reports.shifts') }}" class="inline-flex h-10 items-center gap-2 rounded-md bg-emerald-600 px-3.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 19V9m5 10V5m5 14v-7m5 7V3" /></svg>
                        Shift report
                    </a>
                @endcan
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <dl class="grid grid-cols-2 divide-x divide-y divide-slate-200 dark:divide-slate-800 lg:grid-cols-4 lg:divide-y-0">
                <div class="p-4 sm:p-5"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Open now</dt><dd class="mt-2 text-2xl font-bold tabular-nums {{ $summary['open'] > 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-950 dark:text-white' }}">{{ number_format($summary['open']) }}</dd><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Active registers</p></div>
                <div class="p-4 sm:p-5"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Closed today</dt><dd class="mt-2 text-2xl font-bold tabular-nums text-slate-950 dark:text-white">{{ number_format($summary['closed_today']) }}</dd><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Reconciled shifts</p></div>
                <div class="p-4 sm:p-5"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Results</dt><dd class="mt-2 text-2xl font-bold tabular-nums text-slate-950 dark:text-white">{{ number_format($summary['results']) }}</dd><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Matching current filters</p></div>
                <div class="p-4 sm:p-5"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Net variance</dt><dd class="mt-2 truncate text-2xl font-bold tabular-nums {{ $summary['net_variance']->isZero() ? 'text-slate-950 dark:text-white' : ($summary['net_variance']->isNegative() ? 'text-red-600 dark:text-red-400' : 'text-amber-700 dark:text-amber-400') }}">{{ $formatMoney($summary['net_variance']) }}</dd><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Closed filtered shifts</p></div>
            </dl>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-[minmax(15rem,1fr)_12rem_13rem_11rem_11rem_auto] 2xl:items-end">
                <div class="min-w-0">
                    <label for="shift-search" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Search</label>
                    <div class="relative"><svg xmlns="http://www.w3.org/2000/svg" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m2.35-5.65a8 8 0 11-16 0 8 8 0 0116 0z" /></svg><input wire:model.live.debounce.300ms="search" id="shift-search" type="search" placeholder="Terminal or operator" class="h-10 w-full rounded-md border border-slate-300 bg-white pl-9 pr-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-950"></div>
                </div>
                <div class="min-w-0">
                    <label for="shift-status" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Status</label>
                    <select wire:model.live="status" id="shift-status" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-950"><option value="">All statuses</option><option value="open">Open</option><option value="closed">Closed</option></select>
                </div>
                <div class="min-w-0">
                    <label for="shift-terminal" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Terminal</label>
                    <select wire:model.live="terminal" id="shift-terminal" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-950"><option value="">All terminals</option>@foreach ($terminals as $availableTerminal)<option value="{{ $availableTerminal->id }}">{{ $availableTerminal->name }}</option>@endforeach</select>
                </div>
                <div class="min-w-0">
                    <label for="shift-period" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Opened</label>
                    <select wire:model.live="period" id="shift-period" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-950"><option value="">Any time</option><option value="today">Today</option><option value="7_days">Last 7 days</option><option value="30_days">Last 30 days</option></select>
                </div>
                <div class="min-w-0">
                    <label for="shift-sort" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Sort</label>
                    <select wire:model.live="sort" id="shift-sort" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-950"><option value="newest">Newest first</option><option value="oldest">Oldest first</option><option value="variance">Largest variance</option></select>
                </div>
                @if ($hasFilters)
                    <button type="button" wire:click="clearFilters" class="h-10 rounded-md px-3 text-sm font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">Clear</button>
                @endif
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-950/50 dark:text-slate-400">
                        <tr><th class="w-10 px-3 py-3"></th><th class="px-3 py-3 font-semibold">Terminal</th><th class="px-3 py-3 font-semibold">Operator</th><th class="px-3 py-3 font-semibold">Opened</th><th class="px-3 py-3 font-semibold">Duration</th><th class="px-3 py-3 text-right font-semibold">Sales</th><th class="px-3 py-3 text-right font-semibold">Counted cash</th><th class="px-3 py-3 text-right font-semibold">Variance</th><th class="px-3 py-3 text-right font-semibold">Status</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($shifts as $shift)
                            <tr wire:key="shift-row-{{ $shift->id }}" class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="px-3 py-3"><button type="button" wire:click="toggle({{ $shift->id }})" class="grid h-8 w-8 place-items-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-white" aria-label="{{ $expandedShiftId === $shift->id ? 'Hide' : 'Show' }} reconciliation details" aria-expanded="{{ $expandedShiftId === $shift->id ? 'true' : 'false' }}"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform {{ $expandedShiftId === $shift->id ? 'rotate-90' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg></button></td>
                                <td class="px-3 py-3"><div class="font-semibold text-slate-900 dark:text-white">{{ $shift->terminal->name }}</div><div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $shift->terminal->stockLocation->name }} · {{ $shift->terminal->code }}</div></td>
                                <td class="whitespace-nowrap px-3 py-3 text-slate-700 dark:text-slate-300">{{ $shift->openedBy->name }}</td>
                                <td class="whitespace-nowrap px-3 py-3"><div class="text-slate-700 dark:text-slate-300">{{ $shift->opened_at->format('d M Y') }}</div><div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $shift->opened_at->format('g:i A') }}</div></td>
                                <td class="whitespace-nowrap px-3 py-3 tabular-nums text-slate-600 dark:text-slate-300">{{ $formatDuration($shift) }}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-right"><div class="font-semibold tabular-nums text-slate-900 dark:text-white">{{ $formatMoney($shift->sales_total ?? '0') }}</div><div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ number_format($shift->sales_count) }} sales</div></td>
                                <td class="whitespace-nowrap px-3 py-3 text-right font-medium tabular-nums text-slate-700 dark:text-slate-300">{{ $shift->counted_cash ? $formatMoney($shift->counted_cash) : '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-right font-semibold tabular-nums {{ $shift->cash_variance === null || $shift->cash_variance->isZero() ? 'text-slate-500 dark:text-slate-400' : ($shift->cash_variance->isNegative() ? 'text-red-600 dark:text-red-400' : 'text-amber-700 dark:text-amber-400') }}">{{ $shift->cash_variance ? $formatMoney($shift->cash_variance) : '—' }}</td>
                                <td class="px-3 py-3 text-right"><span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ $shift->status === 'open' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}"><span class="h-1.5 w-1.5 rounded-full {{ $shift->status === 'open' ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>{{ ucfirst($shift->status) }}</span></td>
                            </tr>
                            @if ($expandedShiftId === $shift->id)
                                <tr wire:key="shift-detail-{{ $shift->id }}" class="bg-slate-50/80 dark:bg-slate-950/40"><td colspan="9" class="px-5 py-4"><div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-6"><div><p class="text-xs uppercase text-slate-500">Opening float</p><p class="mt-1 font-semibold tabular-nums">{{ $formatMoney($shift->opening_float) }}</p></div><div><p class="text-xs uppercase text-slate-500">Expected cash</p><p class="mt-1 font-semibold tabular-nums">{{ $shift->expected_cash ? $formatMoney($shift->expected_cash) : 'Pending' }}</p></div><div><p class="text-xs uppercase text-slate-500">Cash dropped</p><p class="mt-1 font-semibold tabular-nums">{{ $formatMoney($shift->cash_dropped) }}</p></div><div><p class="text-xs uppercase text-slate-500">Expected non-cash</p><p class="mt-1 font-semibold tabular-nums">{{ $shift->expected_non_cash ? $formatMoney($shift->expected_non_cash) : '—' }}</p></div><div><p class="text-xs uppercase text-slate-500">Counted non-cash</p><p class="mt-1 font-semibold tabular-nums">{{ $shift->counted_non_cash ? $formatMoney($shift->counted_non_cash) : '—' }}</p></div><div><p class="text-xs uppercase text-slate-500">Closed by</p><p class="mt-1 font-semibold">{{ $shift->closedBy?->name ?? 'Still open' }}</p></div></div>@if ($shift->note)<div class="mt-4 border-t border-slate-200 pt-3 text-sm text-slate-600 dark:border-slate-800 dark:text-slate-300"><span class="font-semibold text-slate-700 dark:text-slate-200">Note:</span> {{ $shift->note }}</div>@endif</td></tr>
                            @endif
                        @empty
                            <tr><td colspan="9" class="px-5 py-14 text-center text-sm text-slate-500 dark:text-slate-400">No shifts match the current filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-slate-200 dark:divide-slate-800 md:hidden">
                @forelse ($shifts as $shift)
                    <article wire:key="shift-card-{{ $shift->id }}">
                        <button type="button" wire:click="toggle({{ $shift->id }})" class="w-full p-4 text-left">
                            <div class="flex items-start justify-between gap-3"><div class="min-w-0"><div class="flex items-center gap-2"><h3 class="truncate text-sm font-semibold text-slate-950 dark:text-white">{{ $shift->terminal->name }}</h3><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $shift->status === 'open' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}">{{ ucfirst($shift->status) }}</span></div><p class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400">{{ $shift->openedBy->name }} · {{ $shift->opened_at->format('d M Y, g:i A') }}</p></div><svg xmlns="http://www.w3.org/2000/svg" class="mt-1 h-4 w-4 shrink-0 text-slate-400 transition-transform {{ $expandedShiftId === $shift->id ? 'rotate-90' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg></div>
                            <dl class="mt-4 grid grid-cols-3 gap-3"><div><dt class="text-xs text-slate-500 dark:text-slate-400">Duration</dt><dd class="mt-1 text-sm font-semibold tabular-nums text-slate-800 dark:text-slate-200">{{ $formatDuration($shift) }}</dd></div><div><dt class="text-xs text-slate-500 dark:text-slate-400">Sales</dt><dd class="mt-1 truncate text-sm font-semibold tabular-nums text-slate-800 dark:text-slate-200">{{ $formatMoney($shift->sales_total ?? '0') }}</dd></div><div class="text-right"><dt class="text-xs text-slate-500 dark:text-slate-400">Variance</dt><dd class="mt-1 truncate text-sm font-semibold tabular-nums {{ $shift->cash_variance === null || $shift->cash_variance->isZero() ? 'text-slate-500 dark:text-slate-400' : ($shift->cash_variance->isNegative() ? 'text-red-600 dark:text-red-400' : 'text-amber-700 dark:text-amber-400') }}">{{ $shift->cash_variance ? $formatMoney($shift->cash_variance) : '—' }}</dd></div></dl>
                        </button>
                        @if ($expandedShiftId === $shift->id)
                            <div class="border-t border-slate-200 bg-slate-50 px-4 py-4 dark:border-slate-800 dark:bg-slate-950/40"><dl class="grid grid-cols-2 gap-4 text-sm"><div><dt class="text-xs text-slate-500">Opening float</dt><dd class="mt-1 font-semibold tabular-nums">{{ $formatMoney($shift->opening_float) }}</dd></div><div><dt class="text-xs text-slate-500">Counted cash</dt><dd class="mt-1 font-semibold tabular-nums">{{ $shift->counted_cash ? $formatMoney($shift->counted_cash) : '—' }}</dd></div><div><dt class="text-xs text-slate-500">Expected cash</dt><dd class="mt-1 font-semibold tabular-nums">{{ $shift->expected_cash ? $formatMoney($shift->expected_cash) : 'Pending' }}</dd></div><div><dt class="text-xs text-slate-500">Cash dropped</dt><dd class="mt-1 font-semibold tabular-nums">{{ $formatMoney($shift->cash_dropped) }}</dd></div><div><dt class="text-xs text-slate-500">Closed by</dt><dd class="mt-1 font-semibold">{{ $shift->closedBy?->name ?? 'Still open' }}</dd></div><div><dt class="text-xs text-slate-500">Location</dt><dd class="mt-1 font-semibold">{{ $shift->terminal->stockLocation->name }}</dd></div></dl>@if ($shift->note)<p class="mt-4 border-t border-slate-200 pt-3 text-sm text-slate-600 dark:border-slate-800 dark:text-slate-300"><span class="font-semibold">Note:</span> {{ $shift->note }}</p>@endif</div>
                        @endif
                    </article>
                @empty
                    <div class="px-5 py-14 text-center text-sm text-slate-500 dark:text-slate-400">No shifts match the current filters.</div>
                @endforelse
            </div>
        </section>

        @if ($shifts->hasPages())
            <div>{{ $shifts->links() }}</div>
        @endif
    </div>
</div>
