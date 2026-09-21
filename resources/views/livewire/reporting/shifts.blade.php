<div>
    @section('title', 'Shift / cash report')

    @php
        $formatMoney = static fn ($money) => \App\Support\Money\Money::format(\App\Support\Money\Money::of($money));
        $formatDuration = static function ($shift) {
            $minutes = (int) $shift->opened_at->diffInMinutes($shift->closed_at ?? now());
            $hours = intdiv($minutes, 60);

            return $hours > 0 ? $hours.'h '.($minutes % 60).'m' : $minutes.'m';
        };
        $exportParameters = array_filter([
            'from' => $from,
            'to' => $to,
            'terminal_id' => $terminal_id,
            'status' => $status,
        ], static fn ($value) => $value !== null && $value !== '');
    @endphp

    <div class="mx-auto max-w-7xl space-y-5">
        <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-400">Reports</p>
                <h2 class="text-2xl font-bold text-slate-950 dark:text-white">Shift and cash report</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Analyze register activity and drawer reconciliation.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('shift.history') }}" class="inline-flex h-10 items-center gap-2 rounded-md border border-slate-300 bg-white px-3.5 text-sm font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 2m6-2a9 9 0 11-3-6.7M21 3v6h-6" /></svg>
                    Shift history
                </a>
                <a href="{{ route('reports.shifts.export', $exportParameters) }}" class="inline-flex h-10 items-center gap-2 rounded-md border border-slate-300 bg-white px-3.5 text-sm font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M5 19h14" /></svg>
                    CSV
                </a>
                <a href="{{ route('reports.shifts.export-pdf', $exportParameters) }}" target="_blank" rel="noopener" class="inline-flex h-10 items-center gap-2 rounded-md bg-emerald-600 px-3.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l5 5v13H7zM14 3v5h5M10 14h6M10 17h4" /></svg>
                    PDF
                </a>
            </div>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end">
                <div class="grid flex-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div><label for="from" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">From</label><input wire:model.live.debounce.300ms="from" id="from" type="date" max="{{ $to }}" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-950"></div>
                    <div><label for="to" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">To</label><input wire:model.live.debounce.300ms="to" id="to" type="date" min="{{ $from }}" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-950"></div>
                    <div><label for="terminal_id" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Terminal</label><select wire:model.live="terminal_id" id="terminal_id" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-950"><option value="">All terminals</option>@foreach ($terminals as $terminal)<option value="{{ $terminal->id }}">{{ $terminal->name }} · {{ $terminal->stockLocation->name }}</option>@endforeach</select></div>
                    <div><label for="report-status" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Status</label><select wire:model.live="status" id="report-status" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-950"><option value="">All statuses</option><option value="open">Open</option><option value="closed">Closed</option></select></div>
                </div>
                <div class="flex h-10 shrink-0 rounded-md border border-slate-300 bg-white p-1 shadow-sm dark:border-slate-700 dark:bg-slate-950">
                    <button type="button" wire:click="setRange('today')" class="rounded px-3 text-xs font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">Today</button>
                    <button type="button" wire:click="setRange('7_days')" class="rounded px-3 text-xs font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">7 days</button>
                    <button type="button" wire:click="setRange('month')" class="rounded px-3 text-xs font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">This month</button>
                </div>
                @if ($terminal_id || $status !== '' || $from !== now()->startOfMonth()->toDateString() || $to !== now()->toDateString())
                    <button type="button" wire:click="clearFilters" class="h-10 shrink-0 rounded-md px-3 text-sm font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">Reset</button>
                @endif
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <dl class="grid grid-cols-2 divide-x divide-y divide-slate-200 dark:divide-slate-800 lg:grid-cols-4 lg:divide-y-0">
                <div class="p-4 sm:p-5"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Sales</dt><dd class="mt-2 truncate text-2xl font-bold tabular-nums text-slate-950 dark:text-white">{{ $formatMoney($summary['sales_total']) }}</dd><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ number_format($summary['sale_count']) }} completed transactions</p></div>
                <div class="p-4 sm:p-5"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Shifts</dt><dd class="mt-2 text-2xl font-bold tabular-nums text-slate-950 dark:text-white">{{ number_format($summary['shift_count']) }}</dd><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ number_format($summary['open_count']) }} still open</p></div>
                <div class="p-4 sm:p-5"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Counted cash</dt><dd class="mt-2 truncate text-2xl font-bold tabular-nums text-slate-950 dark:text-white">{{ $formatMoney($summary['counted_cash']) }}</dd><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Across closed shifts</p></div>
                <div class="p-4 sm:p-5"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Net variance</dt><dd class="mt-2 truncate text-2xl font-bold tabular-nums {{ $summary['cash_variance']->isZero() ? 'text-slate-950 dark:text-white' : ($summary['cash_variance']->isNegative() ? 'text-red-600 dark:text-red-400' : 'text-amber-700 dark:text-amber-400') }}">{{ $formatMoney($summary['cash_variance']) }}</dd><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Over / short combined</p></div>
            </dl>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="hidden overflow-x-auto lg:block">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-950/50 dark:text-slate-400"><tr><th class="w-10 px-3 py-3"></th><th class="px-3 py-3 font-semibold">Terminal / operator</th><th class="px-3 py-3 font-semibold">Opened</th><th class="px-3 py-3 font-semibold">Duration</th><th class="px-3 py-3 text-right font-semibold">Sales</th><th class="px-3 py-3 text-right font-semibold">Expected cash</th><th class="px-3 py-3 text-right font-semibold">Counted cash</th><th class="px-3 py-3 text-right font-semibold">Variance</th><th class="px-3 py-3 text-right font-semibold">Status</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($shifts as $shift)
                            <tr wire:key="report-shift-{{ $shift->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="px-3 py-3"><button type="button" wire:click="toggle({{ $shift->id }})" class="grid h-8 w-8 place-items-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-white" aria-label="Toggle shift details"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform {{ $expandedShiftId === $shift->id ? 'rotate-90' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg></button></td>
                                <td class="px-3 py-3"><div class="font-semibold text-slate-900 dark:text-white">{{ $shift->terminal?->name ?? 'Deleted terminal' }}</div><div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $shift->openedBy?->name ?? 'Unknown' }}@if ($shift->terminal?->stockLocation) · {{ $shift->terminal->stockLocation->name }}@endif</div></td>
                                <td class="whitespace-nowrap px-3 py-3"><div class="text-slate-700 dark:text-slate-300">{{ $shift->opened_at->format('d M Y') }}</div><div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $shift->opened_at->format('g:i A') }}</div></td>
                                <td class="whitespace-nowrap px-3 py-3 tabular-nums text-slate-600 dark:text-slate-300">{{ $formatDuration($shift) }}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-right"><div class="font-semibold tabular-nums text-slate-900 dark:text-white">{{ $formatMoney($shift->sales_total ?? '0') }}</div><div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ number_format($shift->sales_count) }} sales</div></td>
                                <td class="whitespace-nowrap px-3 py-3 text-right font-medium tabular-nums text-slate-700 dark:text-slate-300">{{ $shift->expected_cash ? $formatMoney($shift->expected_cash) : '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-right font-medium tabular-nums text-slate-700 dark:text-slate-300">{{ $shift->counted_cash ? $formatMoney($shift->counted_cash) : '—' }}</td>
                                <td class="whitespace-nowrap px-3 py-3 text-right font-semibold tabular-nums {{ $shift->cash_variance === null || $shift->cash_variance->isZero() ? 'text-slate-500 dark:text-slate-400' : ($shift->cash_variance->isNegative() ? 'text-red-600 dark:text-red-400' : 'text-amber-700 dark:text-amber-400') }}">{{ $shift->cash_variance ? $formatMoney($shift->cash_variance) : '—' }}</td>
                                <td class="px-3 py-3 text-right"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $shift->status === 'open' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}">{{ ucfirst($shift->status) }}</span></td>
                            </tr>
                            @if ($expandedShiftId === $shift->id)
                                <tr wire:key="report-shift-detail-{{ $shift->id }}" class="bg-slate-50/80 dark:bg-slate-950/40"><td colspan="9" class="p-5"><div class="grid gap-6 lg:grid-cols-2">
                                    <div><h4 class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Denomination breakdown</h4><div class="mt-2 overflow-hidden rounded-md border border-slate-200 dark:border-slate-800"><table class="w-full text-sm"><tbody class="divide-y divide-slate-100 dark:divide-slate-800">@forelse ($denominationBreakdown as $count)<tr><td class="px-3 py-2 text-slate-700 dark:text-slate-300">{{ $formatMoney($count->denomination) }}</td><td class="px-3 py-2 text-center text-slate-500">× {{ $count->count }}</td><td class="px-3 py-2 text-right font-semibold tabular-nums">{{ $formatMoney($count->subtotal) }}</td></tr>@empty<tr><td class="px-3 py-6 text-center text-slate-500">No cash count recorded.</td></tr>@endforelse</tbody></table></div></div>
                                    <div><h4 class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Cash movements</h4><div class="mt-2 overflow-hidden rounded-md border border-slate-200 dark:border-slate-800"><table class="w-full text-sm"><tbody class="divide-y divide-slate-100 dark:divide-slate-800">@forelse ($cashMovements as $movement)<tr><td class="px-3 py-2"><span class="font-semibold {{ $movement->direction === 'in' ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">{{ $movement->direction === 'in' ? 'Cash in' : 'Cash out' }}</span><span class="ml-2 text-slate-600 dark:text-slate-300">{{ $movement->reason }}</span></td><td class="px-3 py-2 text-right font-semibold tabular-nums">{{ $formatMoney($movement->amount) }}</td></tr>@empty<tr><td class="px-3 py-6 text-center text-slate-500">No cash movements.</td></tr>@endforelse</tbody></table></div></div>
                                </div></td></tr>
                            @endif
                        @empty
                            <tr><td colspan="9" class="px-5 py-14 text-center text-sm text-slate-500 dark:text-slate-400">No shifts found in this reporting period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-slate-200 dark:divide-slate-800 lg:hidden">
                @forelse ($shifts as $shift)
                    <article wire:key="report-shift-card-{{ $shift->id }}">
                        <button type="button" wire:click="toggle({{ $shift->id }})" class="w-full p-4 text-left">
                            <div class="flex items-start justify-between gap-3"><div class="min-w-0"><div class="flex items-center gap-2"><h3 class="truncate text-sm font-semibold text-slate-950 dark:text-white">{{ $shift->terminal?->name ?? 'Deleted terminal' }}</h3><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $shift->status === 'open' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}">{{ ucfirst($shift->status) }}</span></div><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $shift->openedBy?->name ?? 'Unknown' }} · {{ $shift->opened_at->format('d M Y, g:i A') }}</p></div><svg xmlns="http://www.w3.org/2000/svg" class="mt-1 h-4 w-4 shrink-0 text-slate-400 transition-transform {{ $expandedShiftId === $shift->id ? 'rotate-90' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg></div>
                            <dl class="mt-4 grid grid-cols-3 gap-3"><div><dt class="text-xs text-slate-500">Duration</dt><dd class="mt-1 text-sm font-semibold tabular-nums">{{ $formatDuration($shift) }}</dd></div><div><dt class="text-xs text-slate-500">Sales</dt><dd class="mt-1 truncate text-sm font-semibold tabular-nums">{{ $formatMoney($shift->sales_total ?? '0') }}</dd></div><div class="text-right"><dt class="text-xs text-slate-500">Variance</dt><dd class="mt-1 truncate text-sm font-semibold tabular-nums {{ $shift->cash_variance === null || $shift->cash_variance->isZero() ? 'text-slate-500' : ($shift->cash_variance->isNegative() ? 'text-red-600 dark:text-red-400' : 'text-amber-700 dark:text-amber-400') }}">{{ $shift->cash_variance ? $formatMoney($shift->cash_variance) : '—' }}</dd></div></dl>
                        </button>
                        @if ($expandedShiftId === $shift->id)
                            <div class="space-y-5 border-t border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/40"><dl class="grid grid-cols-2 gap-4 text-sm"><div><dt class="text-xs text-slate-500">Expected cash</dt><dd class="mt-1 font-semibold tabular-nums">{{ $shift->expected_cash ? $formatMoney($shift->expected_cash) : '—' }}</dd></div><div><dt class="text-xs text-slate-500">Counted cash</dt><dd class="mt-1 font-semibold tabular-nums">{{ $shift->counted_cash ? $formatMoney($shift->counted_cash) : '—' }}</dd></div></dl><div><h4 class="text-xs font-semibold uppercase text-slate-500">Denominations</h4><div class="mt-2 space-y-1">@forelse ($denominationBreakdown as $count)<div class="flex justify-between text-sm"><span>{{ $formatMoney($count->denomination) }} × {{ $count->count }}</span><strong>{{ $formatMoney($count->subtotal) }}</strong></div>@empty<p class="text-sm text-slate-500">No cash count recorded.</p>@endforelse</div></div><div><h4 class="text-xs font-semibold uppercase text-slate-500">Cash movements</h4><div class="mt-2 space-y-1">@forelse ($cashMovements as $movement)<div class="flex justify-between gap-3 text-sm"><span class="truncate">{{ $movement->direction === 'in' ? 'Cash in' : 'Cash out' }} · {{ $movement->reason }}</span><strong>{{ $formatMoney($movement->amount) }}</strong></div>@empty<p class="text-sm text-slate-500">No cash movements.</p>@endforelse</div></div></div>
                        @endif
                    </article>
                @empty
                    <div class="px-5 py-14 text-center text-sm text-slate-500 dark:text-slate-400">No shifts found in this reporting period.</div>
                @endforelse
            </div>
        </section>

        @if ($shifts->hasPages())<div>{{ $shifts->links() }}</div>@endif
    </div>
</div>
