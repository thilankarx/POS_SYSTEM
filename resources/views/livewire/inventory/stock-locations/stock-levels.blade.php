<div>
    @section('title', $stockLocation->name . ' — Stock levels')

    <div class="mx-auto max-w-7xl space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <a href="{{ route('stock-locations.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 transition hover:text-emerald-700 dark:text-slate-400 dark:hover:text-emerald-300">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6M9 12h12" /></svg>
                    Stock locations
                </a>
                <div class="mt-2 flex flex-wrap items-center gap-2.5">
                    <h2 class="text-2xl font-bold tracking-tight text-slate-950 dark:text-white">{{ $stockLocation->name }}</h2>
                    <span class="rounded-md bg-slate-100 px-2 py-1 font-mono text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $stockLocation->code }}</span>
                    @if ($stockLocation->is_default)
                        <span class="rounded-md bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300">Default location</span>
                    @endif
                </div>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Stock position and availability for this location.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('inventory.adjust')
                    <a href="{{ route('stock-adjustments.create', ['stock_location_id' => $stockLocation->id]) }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-3.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-7-7h14" /></svg>Adjust stock
                    </a>
                @endcan
                @can('inventory.transfer')
                    <a href="{{ route('stock-transfers.create', ['from_location_id' => $stockLocation->id]) }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-emerald-700 px-3.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 dark:bg-emerald-600 dark:hover:bg-emerald-500">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h13l-3-3m3 3-3 3M17 17H4l3 3m-3-3 3-3" /></svg>Transfer stock
                    </a>
                @endcan
            </div>
        </header>

        <section aria-label="Stock summary" class="grid grid-cols-2 divide-x divide-y divide-slate-200 overflow-hidden rounded-lg border border-slate-200 bg-white sm:grid-cols-3 sm:divide-y-0 xl:grid-cols-6 dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
            <div class="px-4 py-3.5"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">On hand</p><p class="mt-1 text-xl font-bold tabular-nums text-slate-950 dark:text-white">{{ number_format($summary['on_hand'], 3, '.', ',') }}</p></div>
            <div class="px-4 py-3.5"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Reserved</p><p class="mt-1 text-xl font-bold tabular-nums text-amber-700 dark:text-amber-300">{{ number_format($summary['reserved'], 3, '.', ',') }}</p></div>
            <div class="px-4 py-3.5"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Available</p><p class="mt-1 text-xl font-bold tabular-nums {{ $summary['available'] < 0 ? 'text-rose-700 dark:text-rose-300' : 'text-emerald-700 dark:text-emerald-300' }}">{{ number_format($summary['available'], 3, '.', ',') }}</p></div>
            <div class="px-4 py-3.5"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Stocked items</p><p class="mt-1 text-xl font-bold tabular-nums text-slate-950 dark:text-white">{{ number_format($summary['stocked_items']) }}</p></div>
            <div class="px-4 py-3.5"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Low stock</p><p class="mt-1 text-xl font-bold tabular-nums text-amber-700 dark:text-amber-300">{{ number_format($summary['low_stock']) }}</p></div>
            <div class="px-4 py-3.5"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Out of stock</p><p class="mt-1 text-xl font-bold tabular-nums text-rose-700 dark:text-rose-300">{{ number_format($summary['out_of_stock']) }}</p></div>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-3 border-b border-slate-200 p-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Item stock</h3>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Available quantity is on hand minus reserved stock.</p>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <label class="relative block sm:w-72">
                        <span class="sr-only">Search items</span>
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7" /><path stroke-linecap="round" d="m20 20-4-4" /></svg>
                        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search item or SKU" class="h-10 w-full rounded-md border border-slate-300 bg-white pl-9 pr-3 text-sm text-slate-950 outline-none transition placeholder:text-slate-400 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    </label>
                    <label>
                        <span class="sr-only">Filter stock status</span>
                        <select wire:model.live="availability" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-700 outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 sm:w-44">
                            <option value="">All stock levels</option>
                            <option value="in_stock">In stock</option>
                            <option value="low_stock">At or below reorder level</option>
                            <option value="out_of_stock">Out of stock</option>
                        </select>
                    </label>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500 dark:bg-slate-950/60 dark:text-slate-400">
                        <tr>
                            <th scope="col" class="px-4 py-3 sm:px-5">Item</th>
                            <th scope="col" class="px-4 py-3 text-right">On hand</th>
                            <th scope="col" class="px-4 py-3 text-right">Reserved</th>
                            <th scope="col" class="px-4 py-3 text-right">Available</th>
                            <th scope="col" class="px-4 py-3 text-right">Reorder at</th>
                            <th scope="col" class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($levels as $level)
                            @php
                                $available = (float) $level->quantity - (float) $level->reserved_quantity;
                                $isOut = (float) $level->quantity <= 0;
                                $isLow = ! $isOut && (float) $level->quantity <= (float) $level->item->reorder_level;
                            @endphp
                            <tr class="transition hover:bg-slate-50/70 dark:hover:bg-slate-800/30">
                                <td class="px-4 py-3.5 sm:px-5"><span class="block font-semibold text-slate-900 dark:text-white">{{ $level->item->name }}</span><span class="mt-0.5 block font-mono text-xs text-slate-500 dark:text-slate-400">{{ $level->item->sku }}</span></td>
                                <td class="px-4 py-3.5 text-right font-medium tabular-nums text-slate-800 dark:text-slate-200">{{ number_format((float) $level->quantity, 3, '.', ',') }}</td>
                                <td class="px-4 py-3.5 text-right tabular-nums text-slate-600 dark:text-slate-400">{{ number_format((float) $level->reserved_quantity, 3, '.', ',') }}</td>
                                <td @class(['px-4 py-3.5 text-right font-semibold tabular-nums', 'text-rose-700 dark:text-rose-300' => $available < 0, 'text-slate-800 dark:text-slate-200' => $available >= 0])>{{ number_format($available, 3, '.', ',') }}</td>
                                <td class="px-4 py-3.5 text-right tabular-nums text-slate-600 dark:text-slate-400">{{ number_format((float) $level->item->reorder_level, 3, '.', ',') }}</td>
                                <td class="px-4 py-3.5">
                                    @if ($isOut)
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-800 dark:bg-rose-500/10 dark:text-rose-300"><span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>Out of stock</span>
                                    @elseif ($isLow)
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-500/10 dark:text-amber-300"><span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>Reorder</span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>In stock</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-14 text-center">
                                <div class="mx-auto grid h-11 w-11 place-items-center rounded-md bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h16M7 4v16" /></svg></div>
                                <p class="mt-3 text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $search !== '' || $availability !== '' ? 'No matching stock records' : 'No stock recorded here' }}</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $search !== '' || $availability !== '' ? 'Try another item, SKU, or availability filter.' : 'Stock will appear here after items are received or adjusted into this location.' }}</p>
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($levels->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $levels->links() }}</div>
            @endif
        </section>
    </div>
</div>
