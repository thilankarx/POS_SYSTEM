<div>
    @section('title', 'Sales report')

    @php
        $filterInput = 'h-10 w-full min-w-0 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white';
        $tableHead = 'border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-400';
        $tableCell = 'border-b border-slate-100 px-4 py-3 text-sm dark:border-slate-800';
    @endphp

    <div class="mx-auto max-w-7xl space-y-5">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-400">Reports</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-950 dark:text-white">Sales report</h1>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Track sales performance and compare daily, category, payment, and item results.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('reports.sales.export', ['from' => $from, 'to' => $to, 'stock_location_id' => $stock_location_id]) }}" class="inline-flex h-10 items-center gap-2 rounded-md bg-emerald-700 px-3.5 text-sm font-semibold text-white transition hover:bg-emerald-800 dark:bg-emerald-600 dark:hover:bg-emerald-500">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0 4-4m-4 4-4-4M5 19h14" /></svg>
                    Export CSV
                </a>
                <a href="{{ route('reports.sales.export-pdf', ['from' => $from, 'to' => $to, 'stock_location_id' => $stock_location_id]) }}" target="_blank" rel="noopener" class="inline-flex h-10 items-center gap-2 rounded-md border border-slate-300 bg-white px-3.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l5 5v13H7zM14 3v5h5M10 14h6M10 17h4" /></svg>
                    Export PDF
                </a>
            </div>
        </header>

        <section aria-label="Report filters" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-[minmax(150px,1fr)_minmax(150px,1fr)_minmax(190px,1.2fr)]">
                <div>
                    <label for="from" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">From</label>
                    <input wire:model.live.debounce.300ms="from" id="from" type="date" max="{{ $to }}" class="{{ $filterInput }}">
                </div>
                <div>
                    <label for="to" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">To</label>
                    <input wire:model.live.debounce.300ms="to" id="to" type="date" min="{{ $from }}" class="{{ $filterInput }}">
                </div>
                <div>
                    <label for="stock_location_id" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Stock location</label>
                    <select wire:model.live="stock_location_id" id="stock_location_id" class="{{ $filterInput }}">
                        <option value="">All locations</option>
                        @foreach ($stockLocations as $location)
                            <option value="{{ $location->id }}">{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">Results and export files use the selected date range and location.</p>
        </section>

        <section aria-label="Sales summary" class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <dl class="grid grid-cols-2 divide-x divide-y divide-slate-200 dark:divide-slate-800 lg:grid-cols-4 lg:divide-y-0">
                <div class="p-4 sm:p-5">
                    <dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Transactions</dt>
                    <dd class="mt-2 text-2xl font-bold tabular-nums text-slate-950 dark:text-white">{{ number_format($summary->saleCount) }}</dd>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Completed sales</p>
                </div>
                <div class="p-4 sm:p-5">
                    <dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Sales total</dt>
                    <dd class="mt-2 truncate text-2xl font-bold tabular-nums text-slate-950 dark:text-white">{{ $summary->total }}</dd>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">For selected period</p>
                </div>
                <div class="p-4 sm:p-5">
                    <dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Discounts</dt>
                    <dd class="mt-2 truncate text-2xl font-bold tabular-nums text-slate-950 dark:text-white">{{ $summary->discountTotal }}</dd>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Applied to sales</p>
                </div>
                <div class="p-4 sm:p-5">
                    <dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Estimated margin</dt>
                    <dd class="mt-2 truncate text-2xl font-bold tabular-nums text-emerald-700 dark:text-emerald-400">{{ $summary->margin() }}</dd>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Sales less item cost</p>
                </div>
            </dl>
        </section>

        <div class="grid gap-5 xl:grid-cols-2">
            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <header class="border-b border-slate-200 px-4 py-3 dark:border-slate-800"><h2 class="text-sm font-semibold text-slate-950 dark:text-white">Sales by day</h2><p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Daily transaction count and sales total</p></header>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[420px] text-left">
                        <thead class="{{ $tableHead }}"><tr><th scope="col" class="px-4 py-2.5">Day</th><th scope="col" class="px-4 py-2.5 text-right">Transactions</th><th scope="col" class="px-4 py-2.5 text-right">Total</th></tr></thead>
                        <tbody>
                            @forelse ($byDay as $row)
                                <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50"><td class="{{ $tableCell }} font-medium text-slate-800 dark:text-slate-200">{{ \Illuminate\Support\Carbon::parse($row->day)->format('D, M j, Y') }}</td><td class="{{ $tableCell }} text-right tabular-nums text-slate-600 dark:text-slate-300">{{ number_format((int) $row->sale_count) }}</td><td class="{{ $tableCell }} text-right font-semibold tabular-nums text-slate-900 dark:text-white">{{ $row->total }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-400">No sales found for this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <header class="border-b border-slate-200 px-4 py-3 dark:border-slate-800"><h2 class="text-sm font-semibold text-slate-950 dark:text-white">Sales by category</h2><p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Quantity sold and line revenue</p></header>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[420px] text-left">
                        <thead class="{{ $tableHead }}"><tr><th scope="col" class="px-4 py-2.5">Category</th><th scope="col" class="px-4 py-2.5 text-right">Quantity</th><th scope="col" class="px-4 py-2.5 text-right">Revenue</th></tr></thead>
                        <tbody>
                            @forelse ($byCategory as $row)
                                <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50"><td class="{{ $tableCell }} font-medium text-slate-800 dark:text-slate-200">{{ $row->category_name }}</td><td class="{{ $tableCell }} text-right tabular-nums text-slate-600 dark:text-slate-300">{{ number_format((float) $row->quantity, 2) }}</td><td class="{{ $tableCell }} text-right font-semibold tabular-nums text-slate-900 dark:text-white">{{ $row->line_total }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-400">No category sales found for this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <header class="border-b border-slate-200 px-4 py-3 dark:border-slate-800"><h2 class="text-sm font-semibold text-slate-950 dark:text-white">Payments by method</h2><p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Payment volume by tender type</p></header>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[420px] text-left">
                        <thead class="{{ $tableHead }}"><tr><th scope="col" class="px-4 py-2.5">Payment method</th><th scope="col" class="px-4 py-2.5 text-right">Payments</th><th scope="col" class="px-4 py-2.5 text-right">Amount</th></tr></thead>
                        <tbody>
                            @forelse ($byPaymentMethod as $row)
                                <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50"><td class="{{ $tableCell }} font-medium text-slate-800 dark:text-slate-200">{{ $row->method_name }}</td><td class="{{ $tableCell }} text-right tabular-nums text-slate-600 dark:text-slate-300">{{ number_format((int) $row->payment_count) }}</td><td class="{{ $tableCell }} text-right font-semibold tabular-nums text-slate-900 dark:text-white">{{ $row->amount }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-400">No payments found for this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <header class="border-b border-slate-200 px-4 py-3 dark:border-slate-800"><h2 class="text-sm font-semibold text-slate-950 dark:text-white">Top-selling items</h2><p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Best performers by quantity and sales value</p></header>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[420px] text-left">
                        <thead class="{{ $tableHead }}"><tr><th scope="col" class="px-4 py-2.5">Item</th><th scope="col" class="px-4 py-2.5 text-right">Quantity</th><th scope="col" class="px-4 py-2.5 text-right">Revenue</th></tr></thead>
                        <tbody>
                            @forelse ($topItems as $row)
                                <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50"><td class="{{ $tableCell }} font-medium text-slate-800 dark:text-slate-200">{{ $row->item_name }}</td><td class="{{ $tableCell }} text-right tabular-nums text-slate-600 dark:text-slate-300">{{ number_format((float) $row->quantity, 2) }}</td><td class="{{ $tableCell }} text-right font-semibold tabular-nums text-slate-900 dark:text-white">{{ $row->line_total }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-400">No item sales found for this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>
