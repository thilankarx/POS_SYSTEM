<div>
    @section('title', 'Serial Numbers')

    <div class="mx-auto max-w-7xl space-y-6">
        <header>
            <p class="text-sm font-medium text-emerald-700 dark:text-emerald-400">Inventory</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 dark:text-white">Serial numbers</h2>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Trace serialized items across stock, sales, and returns.</p>
        </header>

        <section aria-label="Serial number totals" class="grid grid-cols-2 divide-x divide-y divide-slate-200 overflow-hidden rounded-lg border border-slate-200 bg-white sm:grid-cols-3 sm:divide-y-0 xl:grid-cols-5 dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
            <div class="px-4 py-3.5"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Total serials</p><p class="mt-1 text-xl font-bold tabular-nums text-slate-950 dark:text-white">{{ number_format($stats['total']) }}</p></div>
            <div class="px-4 py-3.5"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">In stock</p><p class="mt-1 text-xl font-bold tabular-nums text-emerald-700 dark:text-emerald-300">{{ number_format($stats['in_stock']) }}</p></div>
            <div class="px-4 py-3.5"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Sold</p><p class="mt-1 text-xl font-bold tabular-nums text-sky-700 dark:text-sky-300">{{ number_format($stats['sold']) }}</p></div>
            <div class="px-4 py-3.5"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Returned</p><p class="mt-1 text-xl font-bold tabular-nums text-amber-700 dark:text-amber-300">{{ number_format($stats['returned']) }}</p></div>
            <div class="px-4 py-3.5"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Scrapped</p><p class="mt-1 text-xl font-bold tabular-nums text-rose-700 dark:text-rose-300">{{ number_format($stats['scrapped']) }}</p></div>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-3 border-b border-slate-200 p-4 dark:border-slate-800">
                <div class="flex flex-col gap-2 lg:flex-row">
                    <label class="relative block min-w-0 flex-1">
                        <span class="sr-only">Search serial numbers</span>
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7" /><path stroke-linecap="round" d="m20 20-4-4" /></svg>
                        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search serial, item, SKU, or lot" class="h-10 w-full rounded-md border border-slate-300 bg-white pl-9 pr-3 text-sm text-slate-950 outline-none transition placeholder:text-slate-400 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    </label>
                    <label class="lg:w-48">
                        <span class="sr-only">Filter by serial status</span>
                        <select wire:model.live="status" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-700 outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">
                            <option value="">All statuses</option><option value="in_stock">In stock</option><option value="sold">Sold</option><option value="returned">Returned</option><option value="scrapped">Scrapped</option>
                        </select>
                    </label>
                    <label class="lg:w-56">
                        <span class="sr-only">Filter by stock location</span>
                        <select wire:model.live="stock_location_id" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-700 outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">
                            <option value="">All locations</option>
                            @foreach ($stockLocations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach
                        </select>
                    </label>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $serialNumbers->total() }} {{ \Illuminate\Support\Str::plural('serial number', $serialNumbers->total()) }}</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500 dark:bg-slate-950/60 dark:text-slate-400">
                        <tr>
                            <th scope="col" class="px-4 py-3 sm:px-5">Serial</th>
                            <th scope="col" class="px-4 py-3">Item</th>
                            <th scope="col" class="px-4 py-3">Status</th>
                            <th scope="col" class="px-4 py-3">Location</th>
                            <th scope="col" class="px-4 py-3">Lot</th>
                            <th scope="col" class="px-4 py-3">Registered</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($serialNumbers as $serialNumber)
                            @php
                                $statusStyles = match ($serialNumber->status) {
                                    'in_stock' => 'bg-emerald-50 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300',
                                    'sold' => 'bg-sky-50 text-sky-800 dark:bg-sky-500/10 dark:text-sky-300',
                                    'returned' => 'bg-amber-50 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300',
                                    'scrapped' => 'bg-rose-50 text-rose-800 dark:bg-rose-500/10 dark:text-rose-300',
                                    default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
                                };
                            @endphp
                            <tr class="transition hover:bg-slate-50/70 dark:hover:bg-slate-800/30">
                                <td class="whitespace-nowrap px-4 py-3.5 font-mono font-semibold text-slate-900 dark:text-white sm:px-5">{{ $serialNumber->serial }}</td>
                                <td class="px-4 py-3.5"><span class="block font-medium text-slate-800 dark:text-slate-200">{{ $serialNumber->item?->name ?? 'Item removed' }}</span><span class="mt-0.5 block font-mono text-xs text-slate-500 dark:text-slate-400">{{ $serialNumber->item?->sku ?? '—' }}</span></td>
                                <td class="px-4 py-3.5"><span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusStyles }}">{{ ucfirst(str_replace('_', ' ', $serialNumber->status)) }}</span></td>
                                <td class="px-4 py-3.5 text-slate-600 dark:text-slate-400">{{ $serialNumber->stockLocation?->name ?? '—' }}</td>
                                <td class="px-4 py-3.5 font-mono text-xs text-slate-600 dark:text-slate-400">{{ $serialNumber->lot?->lot_number ?? '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3.5 text-xs text-slate-600 dark:text-slate-400">{{ $serialNumber->created_at?->format('M j, Y') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-14 text-center">
                                <div class="mx-auto grid h-11 w-11 place-items-center rounded-md bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M7 5h10M7 9h10M7 13h6M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z" /></svg></div>
                                <p class="mt-3 text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $search !== '' || $status !== '' || $stock_location_id !== '' ? 'No matching serial numbers' : 'No serial numbers recorded' }}</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $search !== '' || $status !== '' || $stock_location_id !== '' ? 'Try changing your search or filters.' : 'Serialized inventory will appear here as items are received.' }}</p>
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($serialNumbers->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $serialNumbers->links() }}</div>
            @endif
        </section>
    </div>
</div>
