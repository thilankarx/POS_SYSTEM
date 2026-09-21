<div>
    @section('title', 'Loyalty Packages')

    <div class="mx-auto max-w-7xl space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-medium text-emerald-700 dark:text-emerald-400">Customer relationships</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 dark:text-white">Loyalty packages</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Manage point earning, redemption value, and expiry rules.</p>
            </div>
            @can('create', \App\Domain\Loyalty\Models\LoyaltyPackage::class)
                <a href="{{ route('loyalty-packages.create') }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-emerald-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 dark:bg-emerald-600 dark:hover:bg-emerald-500">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
                    New package
                </a>
            @endcan
        </header>

        @if (session('status'))
            <div role="status" class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">{{ session('status') }}</div>
        @endif

        <section aria-label="Loyalty package totals" class="grid grid-cols-1 divide-y divide-slate-200 rounded-lg border border-slate-200 bg-white sm:grid-cols-3 sm:divide-x sm:divide-y-0 dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
            <div class="px-5 py-4"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">All packages</p><p class="mt-1 text-2xl font-bold tabular-nums text-slate-950 dark:text-white">{{ number_format($stats['total']) }}</p></div>
            <div class="px-5 py-4"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Active packages</p><p class="mt-1 text-2xl font-bold tabular-nums text-emerald-700 dark:text-emerald-300">{{ number_format($stats['active']) }}</p></div>
            <div class="px-5 py-4"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Enrolled customers</p><p class="mt-1 text-2xl font-bold tabular-nums text-sky-700 dark:text-sky-300">{{ number_format($stats['members']) }}</p></div>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-3 border-b border-slate-200 p-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-col gap-2 sm:flex-row">
                    <label class="relative block sm:w-80">
                        <span class="sr-only">Search loyalty packages</span>
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7" /><path stroke-linecap="round" d="m20 20-4-4" /></svg>
                        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search package name" class="h-10 w-full rounded-md border border-slate-300 bg-white pl-9 pr-3 text-sm text-slate-950 outline-none transition placeholder:text-slate-400 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    </label>
                    <label>
                        <span class="sr-only">Filter by package status</span>
                        <select wire:model.live="active" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-700 outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 sm:w-40">
                            <option value="">All statuses</option><option value="1">Active</option><option value="0">Inactive</option>
                        </select>
                    </label>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $packages->total() }} {{ \Illuminate\Support\Str::plural('package', $packages->total()) }}</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500 dark:bg-slate-950/60 dark:text-slate-400">
                        <tr>
                            <th scope="col" class="px-4 py-3 sm:px-5">Package</th>
                            <th scope="col" class="px-4 py-3 text-right">Earning rate</th>
                            <th scope="col" class="px-4 py-3 text-right">Point value</th>
                            <th scope="col" class="px-4 py-3">Expiry</th>
                            <th scope="col" class="px-4 py-3 text-right">Members</th>
                            <th scope="col" class="px-4 py-3">Status</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($packages as $package)
                            <tr class="transition hover:bg-slate-50/70 dark:hover:bg-slate-800/30">
                                <td class="px-4 py-3.5 sm:px-5">
                                    <a href="{{ route('loyalty-packages.edit', $package) }}" class="font-semibold text-slate-900 decoration-emerald-600 underline-offset-2 hover:text-emerald-700 hover:underline dark:text-white dark:hover:text-emerald-300">{{ $package->name }}</a>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ number_format($package->customers_count) }} enrolled {{ \Illuminate\Support\Str::plural('customer', $package->customers_count) }}</p>
                                </td>
                                <td class="px-4 py-3.5 text-right font-mono tabular-nums text-slate-700 dark:text-slate-300">{{ number_format((float) $package->points_per_currency_unit, 4, '.', ',') }} <span class="text-xs text-slate-500">/ 1</span></td>
                                <td class="px-4 py-3.5 text-right font-mono tabular-nums text-slate-700 dark:text-slate-300">{{ number_format((float) $package->currency_value_per_point, 4, '.', ',') }}</td>
                                <td class="px-4 py-3.5 text-slate-600 dark:text-slate-400">{{ $package->points_expire_after_days !== null ? number_format($package->points_expire_after_days).' '.\Illuminate\Support\Str::plural('day', $package->points_expire_after_days) : 'Never' }}</td>
                                <td class="px-4 py-3.5 text-right font-semibold tabular-nums text-slate-800 dark:text-slate-200">{{ number_format($package->customers_count) }}</td>
                                <td class="px-4 py-3.5"><span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ $package->is_active ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}"><span class="h-1.5 w-1.5 rounded-full {{ $package->is_active ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>{{ $package->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td class="whitespace-nowrap px-4 py-3.5 text-right">
                                    <a href="{{ route('loyalty-packages.edit', $package) }}" class="inline-flex h-9 items-center rounded-md border border-slate-300 px-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Manage</a>
                                    @can('delete', $package)
                                        <button type="button" wire:click="delete({{ $package->id }})" wire:confirm="Delete '{{ $package->name }}'?" aria-label="Delete {{ $package->name }}" title="Delete package" class="ml-1 inline-grid h-9 w-9 place-items-center rounded-md text-slate-400 transition hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-950/40 dark:hover:text-rose-300"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12m-10 0l1 13h6l1-13M9 7V4h6v3" /></svg></button>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-5 py-14 text-center">
                                <div class="mx-auto grid h-11 w-11 place-items-center rounded-md bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18m-5-4h7a3 3 0 000-6H10a3 3 0 010-6h7" /></svg></div>
                                <p class="mt-3 text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $search !== '' || $active !== '' ? 'No matching packages' : 'No loyalty packages yet' }}</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $search !== '' || $active !== '' ? 'Try changing the name search or status filter.' : 'Create a package to define how customers earn and redeem points.' }}</p>
                                @if ($search === '' && $active === '')
                                    @can('create', \App\Domain\Loyalty\Models\LoyaltyPackage::class)
                                        <a href="{{ route('loyalty-packages.create') }}" class="mt-4 inline-flex h-9 items-center gap-1.5 rounded-md bg-emerald-700 px-3.5 text-sm font-semibold text-white hover:bg-emerald-800 dark:bg-emerald-600 dark:hover:bg-emerald-500"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>Create package</a>
                                    @endcan
                                @endif
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($packages->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $packages->links() }}</div>
            @endif
        </section>
    </div>
</div>
