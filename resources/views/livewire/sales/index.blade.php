<div>
    @section('title', 'Sales')

    @php
        $formatMoney = static fn ($money) => \App\Support\Money\Money::format(\App\Support\Money\Money::of($money));
        $hasFilters = $search !== '' || $status !== '' || $type !== '' || $location !== '' || $from !== '' || $to !== '' || $sort !== 'newest';
        $statusClasses = [
            'completed' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
            'voided' => 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300',
            'refunded' => 'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300',
            'partially_refunded' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300',
        ];
    @endphp

    <div class="space-y-6">
        <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-950 dark:text-white">Sales</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Find transactions, receipts, returns, and customer orders.</p>
            </div>
            @can('sales.create')
                <a href="{{ route('pos') }}" class="inline-flex h-10 items-center justify-center gap-2 self-start rounded-md bg-emerald-600 px-3.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700 sm:self-auto">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l3-8H6.4M7 13l-2 4h13M9 21h.01M17 21h.01" /></svg>
                    Open register
                </a>
            @endcan
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <dl class="grid grid-cols-2 divide-x divide-y divide-slate-200 dark:divide-slate-800 lg:grid-cols-4 lg:divide-y-0">
                <div class="p-4 sm:p-5"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Net completed sales</dt><dd class="mt-2 truncate text-2xl font-bold tabular-nums text-slate-950 dark:text-white">{{ $formatMoney($summary['total']) }}</dd><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Current filters</p></div>
                <div class="p-4 sm:p-5"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Completed</dt><dd class="mt-2 text-2xl font-bold tabular-nums text-slate-950 dark:text-white">{{ number_format($summary['completed']) }}</dd><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Revenue transactions</p></div>
                <div class="p-4 sm:p-5"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Refund activity</dt><dd class="mt-2 text-2xl font-bold tabular-nums {{ $summary['refunds'] > 0 ? 'text-violet-700 dark:text-violet-400' : 'text-slate-950 dark:text-white' }}">{{ number_format($summary['refunds']) }}</dd><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Returns and refunds</p></div>
                <div class="p-4 sm:p-5"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Voided</dt><dd class="mt-2 text-2xl font-bold tabular-nums {{ $summary['voided'] > 0 ? 'text-red-600 dark:text-red-400' : 'text-slate-950 dark:text-white' }}">{{ number_format($summary['voided']) }}</dd><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Cancelled transactions</p></div>
            </dl>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-[minmax(15rem,1fr)_11rem_11rem_13rem_10rem_10rem_11rem_auto] 2xl:items-end">
                <div class="min-w-0">
                    <label for="sales-search" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Search</label>
                    <div class="relative"><svg xmlns="http://www.w3.org/2000/svg" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35m2.35-5.65a8 8 0 11-16 0 8 8 0 0116 0z" /></svg><input wire:model.live.debounce.300ms="search" id="sales-search" type="search" placeholder="Sale, invoice, or customer" class="h-10 w-full rounded-md border border-slate-300 bg-white pl-9 pr-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-950"></div>
                </div>
                <div class="min-w-0"><label for="sales-status" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Status</label><select wire:model.live="status" id="sales-status" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-950"><option value="">All statuses</option><option value="completed">Completed</option><option value="voided">Voided</option><option value="refunded">Refunded</option><option value="partially_refunded">Partially refunded</option></select></div>
                <div class="min-w-0"><label for="sales-type" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Type</label><select wire:model.live="type" id="sales-type" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-950"><option value="">All types</option><option value="pos">POS sale</option><option value="invoice">Invoice</option><option value="quote">Quote</option><option value="work_order">Work order</option><option value="return">Return</option></select></div>
                <div class="min-w-0"><label for="sales-location" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Location</label><select wire:model.live="location" id="sales-location" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-950"><option value="">All assigned locations</option>@foreach ($stockLocations as $stockLocation)<option value="{{ $stockLocation->id }}">{{ $stockLocation->name }}</option>@endforeach</select></div>
                <div class="min-w-0"><label for="sales-from" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">From</label><input wire:model.live.debounce.300ms="from" id="sales-from" type="date" max="{{ $to ?: null }}" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-950"></div>
                <div class="min-w-0"><label for="sales-to" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">To</label><input wire:model.live.debounce.300ms="to" id="sales-to" type="date" min="{{ $from ?: null }}" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-950"></div>
                <div class="min-w-0"><label for="sales-sort" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Sort</label><select wire:model.live="sort" id="sales-sort" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-950"><option value="newest">Newest first</option><option value="oldest">Oldest first</option><option value="total_desc">Highest total</option></select></div>
                @if ($hasFilters)<button type="button" wire:click="clearFilters" class="h-10 rounded-md px-3 text-sm font-semibold text-slate-600 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">Clear</button>@endif
            </div>
            <div class="mt-3 flex gap-2"><button type="button" wire:click="setRange('today')" class="rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:border-emerald-400 hover:text-emerald-700 dark:border-slate-700 dark:text-slate-300">Today</button><button type="button" wire:click="setRange('7_days')" class="rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:border-emerald-400 hover:text-emerald-700 dark:border-slate-700 dark:text-slate-300">7 days</button><button type="button" wire:click="setRange('all')" class="rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:border-emerald-400 hover:text-emerald-700 dark:border-slate-700 dark:text-slate-300">All time</button></div>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="hidden overflow-x-auto lg:block">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-950/50 dark:text-slate-400"><tr><th class="px-4 py-3 font-semibold">Transaction</th><th class="px-4 py-3 font-semibold">Date</th><th class="px-4 py-3 font-semibold">Customer</th><th class="px-4 py-3 font-semibold">Location / cashier</th><th class="px-4 py-3 font-semibold">Payment</th><th class="px-4 py-3 font-semibold">Status</th><th class="px-4 py-3 text-right font-semibold">Total</th><th class="w-12 px-3 py-3"></th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($sales as $sale)
                            @php
                                $customerName = $sale->customer?->company_name ?: $sale->customer?->person?->full_name;
                                $paymentNames = $sale->payments->where('status', 'captured')->pluck('method.name')->filter()->unique()->join(', ');
                            @endphp
                            <tr wire:key="sale-{{ $sale->id }}" class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="px-4 py-3"><a href="{{ route('sales.show', $sale) }}" class="font-semibold text-slate-900 hover:text-emerald-700 dark:text-white dark:hover:text-emerald-400">{{ $sale->number }}</a><div class="mt-1"><span class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-medium capitalize text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ str($sale->sale_type)->replace('_', ' ') }}</span>@if ($sale->invoice_number)<span class="ml-1 text-xs text-slate-500">{{ $sale->invoice_number }}</span>@endif</div></td>
                                <td class="whitespace-nowrap px-4 py-3"><div class="text-slate-700 dark:text-slate-300">{{ $sale->sold_at->format('d M Y') }}</div><div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $sale->sold_at->format('g:i A') }}</div></td>
                                <td class="max-w-48 truncate px-4 py-3 text-slate-700 dark:text-slate-300">{{ $customerName ?: 'Walk-in' }}</td>
                                <td class="px-4 py-3"><div class="text-slate-700 dark:text-slate-300">{{ $sale->stockLocation?->name ?? 'Deleted location' }}</div><div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $sale->user?->name ?? 'Former user' }}</div></td>
                                <td class="max-w-40 truncate px-4 py-3 text-slate-600 dark:text-slate-300">{{ $paymentNames ?: '—' }}</td>
                                <td class="px-4 py-3"><span class="inline-flex whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold capitalize {{ $statusClasses[$sale->status] ?? 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}">{{ str($sale->status)->replace('_', ' ') }}</span></td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums text-slate-950 dark:text-white">{{ $formatMoney($sale->total) }}</td>
                                <td class="px-3 py-3"><a href="{{ route('sales.show', $sale) }}" class="grid h-8 w-8 place-items-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-emerald-700 dark:hover:bg-slate-800 dark:hover:text-emerald-400" aria-label="View {{ $sale->number }}"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" /></svg></a></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-5 py-14 text-center text-sm text-slate-500 dark:text-slate-400">No sales match the current filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-slate-200 dark:divide-slate-800 lg:hidden">
                @forelse ($sales as $sale)
                    @php $customerName = $sale->customer?->company_name ?: $sale->customer?->person?->full_name; @endphp
                    <a href="{{ route('sales.show', $sale) }}" wire:key="sale-card-{{ $sale->id }}" class="block p-4 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50">
                        <div class="flex items-start justify-between gap-3"><div class="min-w-0"><div class="flex items-center gap-2"><h3 class="truncate text-sm font-semibold text-slate-950 dark:text-white">{{ $sale->number }}</h3><span class="rounded-full px-2 py-0.5 text-xs font-semibold capitalize {{ $statusClasses[$sale->status] ?? 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}">{{ str($sale->status)->replace('_', ' ') }}</span></div><p class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400">{{ $customerName ?: 'Walk-in' }} · {{ $sale->sold_at->format('d M Y, g:i A') }}</p></div><p class="shrink-0 text-sm font-bold tabular-nums text-slate-950 dark:text-white">{{ $formatMoney($sale->total) }}</p></div>
                        <div class="mt-3 flex items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400"><span class="truncate capitalize">{{ str($sale->sale_type)->replace('_', ' ') }} · {{ $sale->stockLocation?->name ?? 'Deleted location' }}</span><span class="shrink-0">{{ $sale->user?->name ?? 'Former user' }}</span></div>
                    </a>
                @empty
                    <div class="px-5 py-14 text-center text-sm text-slate-500 dark:text-slate-400">No sales match the current filters.</div>
                @endforelse
            </div>
        </section>

        @if ($sales->hasPages())<div>{{ $sales->links() }}</div>@endif
    </div>
</div>
