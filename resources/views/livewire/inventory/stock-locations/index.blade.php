<div>
    @section('title', 'Stock Locations')

    <div class="mx-auto max-w-7xl space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-medium text-emerald-700 dark:text-emerald-400">Inventory</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 dark:text-white">Stock locations</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Manage where stock is held, sold, and received.</p>
            </div>
            @can('create', \App\Domain\Inventory\Models\StockLocation::class)
                <a href="{{ route('stock-locations.create') }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-emerald-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 dark:bg-emerald-600 dark:hover:bg-emerald-500">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
                    New location
                </a>
            @endcan
        </header>

        @if (session('status'))
            <div role="status" class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">{{ session('status') }}</div>
        @endif

        <section aria-label="Location summary" class="grid grid-cols-1 divide-y divide-slate-200 rounded-lg border border-slate-200 bg-white sm:grid-cols-3 sm:divide-x sm:divide-y-0 dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
            <div class="px-5 py-4"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Locations</p><p class="mt-1 text-2xl font-bold tabular-nums text-slate-950 dark:text-white">{{ number_format($stats['total']) }}</p></div>
            <div class="px-5 py-4"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Sell from</p><p class="mt-1 text-2xl font-bold tabular-nums text-emerald-700 dark:text-emerald-400">{{ number_format($stats['selling']) }}</p></div>
            <div class="px-5 py-4"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Receive stock</p><p class="mt-1 text-2xl font-bold tabular-nums text-sky-700 dark:text-sky-400">{{ number_format($stats['receiving']) }}</p></div>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-3 border-b border-slate-200 p-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                <label class="relative block sm:w-80">
                    <span class="sr-only">Search locations</span>
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7" /><path stroke-linecap="round" d="m20 20-4-4" /></svg>
                    <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search name or code" class="h-10 w-full rounded-md border border-slate-300 bg-white pl-9 pr-3 text-sm text-slate-950 outline-none transition placeholder:text-slate-400 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                </label>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $stockLocations->total() }} {{ \Illuminate\Support\Str::plural('location', $stockLocations->total()) }}</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500 dark:bg-slate-950/60 dark:text-slate-400">
                        <tr>
                            <th scope="col" class="px-4 py-3 sm:px-5">Location</th>
                            <th scope="col" class="px-4 py-3">On hand</th>
                            <th scope="col" class="px-4 py-3">Operations</th>
                            <th scope="col" class="px-4 py-3">Default</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($stockLocations as $location)
                            <tr class="align-middle transition hover:bg-slate-50/70 dark:hover:bg-slate-800/30">
                                <td class="px-4 py-3.5 sm:px-5">
                                    <div class="flex items-center gap-3">
                                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-md {{ $location->is_default ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}">
                                            <svg class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l8-4v18m0-13h6v13M8 9v.01M8 12v.01M8 15v.01M8 18v.01M16 12v.01M16 15v.01M16 18v.01" /></svg>
                                        </span>
                                        <span class="min-w-0"><span class="block font-semibold text-slate-900 dark:text-white">{{ $location->name }}</span><span class="mt-0.5 block font-mono text-xs text-slate-500 dark:text-slate-400">{{ $location->code }}</span></span>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5">
                                    <p class="font-semibold tabular-nums text-slate-900 dark:text-white">{{ number_format((float) ($location->on_hand_quantity ?? 0), 3, '.', ',') }}</p>
                                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ number_format($location->stocked_items_count) }} stocked {{ \Illuminate\Support\Str::plural('item', $location->stocked_items_count) }}</p>
                                </td>
                                <td class="px-4 py-3.5">
                                    <div class="flex flex-wrap gap-1.5">
                                        <span @class(['rounded-md px-2 py-1 text-xs font-medium', 'bg-emerald-50 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300' => $location->sells, 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' => ! $location->sells])>Sell {{ $location->sells ? 'on' : 'off' }}</span>
                                        <span @class(['rounded-md px-2 py-1 text-xs font-medium', 'bg-sky-50 text-sky-800 dark:bg-sky-500/10 dark:text-sky-300' => $location->receives, 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' => ! $location->receives])>Receive {{ $location->receives ? 'on' : 'off' }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5">
                                    @if ($location->is_default)
                                        <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-800 dark:text-emerald-300"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg>Default</span>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3.5 text-right">
                                    @can('viewStock', $location)
                                        <a href="{{ route('stock-locations.stock', $location) }}" class="inline-flex h-9 items-center rounded-md border border-slate-300 px-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">View stock</a>
                                    @endcan
                                    @can('update', $location)
                                        <a href="{{ route('stock-locations.edit', $location) }}" aria-label="Edit {{ $location->name }}" title="Edit location" class="ml-1 inline-grid h-9 w-9 place-items-center rounded-md text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m16 4 4 4M4 20l4-.8L19 8a2.1 2.1 0 00-3-3L5 16l-1 4z" /></svg></a>
                                    @endcan
                                    @can('delete', $location)
                                        <button type="button" wire:click="delete({{ $location->id }})" wire:confirm="Delete '{{ $location->name }}'?" aria-label="Delete {{ $location->name }}" title="Delete location" class="ml-1 inline-grid h-9 w-9 place-items-center rounded-md text-slate-400 transition hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-950/40 dark:hover:text-rose-300"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12m-10 0l1 13h6l1-13M9 7V4h6v3" /></svg></button>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-14 text-center">
                                <div class="mx-auto grid h-11 w-11 place-items-center rounded-md bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l8-4v18m0-13h6v13" /></svg></div>
                                <p class="mt-3 text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $search !== '' ? 'No matching locations' : 'No stock locations yet' }}</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $search !== '' ? 'Try another name or location code.' : 'Add a stock location to organize where inventory is held.' }}</p>
                                @if ($search === '')
                                    @can('create', \App\Domain\Inventory\Models\StockLocation::class)
                                        <a href="{{ route('stock-locations.create') }}" class="mt-4 inline-flex h-9 items-center gap-1.5 rounded-md bg-emerald-700 px-3.5 text-sm font-semibold text-white hover:bg-emerald-800 dark:bg-emerald-600 dark:hover:bg-emerald-500"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>Add location</a>
                                    @endcan
                                @endif
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($stockLocations->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $stockLocations->links() }}</div>
            @endif
        </section>
    </div>
</div>
