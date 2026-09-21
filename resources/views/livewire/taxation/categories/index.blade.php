<div>
    @section('title', 'Tax categories')

    <div class="mx-auto max-w-7xl space-y-5">
        @if (session('status'))
            <div role="status" class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">{{ session('status') }}</div>
        @endif

        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-400">Tax setup</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-950 dark:text-white">Tax categories</h1>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Manage default tax treatment and the rates applied to sales.</p>
            </div>
            @can('create', \App\Domain\Taxation\Models\TaxCategory::class)
                <a href="{{ route('tax-categories.create') }}" class="inline-flex h-10 items-center justify-center gap-2 self-start rounded-md bg-emerald-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 dark:bg-emerald-600 dark:hover:bg-emerald-500 sm:self-auto">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-7-7h14" /></svg>
                    New tax category
                </a>
            @endcan
        </header>

        <section aria-label="Tax category totals" class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <dl class="grid grid-cols-3 divide-x divide-slate-200 dark:divide-slate-800">
                <div class="p-3 sm:p-4"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Categories</dt><dd class="mt-1.5 text-xl font-bold tabular-nums text-slate-950 dark:text-white">{{ number_format($totalCategories) }}</dd></div>
                <div class="p-3 sm:p-4"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Default categories</dt><dd class="mt-1.5 text-xl font-bold tabular-nums text-emerald-700 dark:text-emerald-400">{{ number_format($defaultCategories) }}</dd></div>
                <div class="p-3 sm:p-4"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Configured rates</dt><dd class="mt-1.5 text-xl font-bold tabular-nums text-slate-950 dark:text-white">{{ number_format($totalRates) }}</dd></div>
            </dl>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-3 border-b border-slate-200 p-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-slate-950 dark:text-white">Category rules</h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ number_format($taxCategories->total()) }} {{ \Illuminate\Support\Str::plural('category', $taxCategories->total()) }} in the list</p>
                </div>
                <div class="relative w-full sm:max-w-sm">
                    <label for="tax-category-search" class="sr-only">Search tax categories</label>
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m16 16 4 4"/></svg>
                    <input wire:model.live.debounce.300ms="search" id="tax-category-search" type="search" autocomplete="off" placeholder="Search categories, codes, or rates" class="h-10 w-full rounded-md border border-slate-300 bg-white pl-9 pr-10 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    @if ($search !== '')
                        <button type="button" wire:click="$set('search', '')" aria-label="Clear search" title="Clear search" class="absolute right-2 top-1/2 grid h-7 w-7 -translate-y-1/2 place-items-center rounded text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-white"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg></button>
                    @endif
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-400">
                        <tr>
                            <th scope="col" class="px-4 py-3">Category</th>
                            <th scope="col" class="px-4 py-3">Code</th>
                            <th scope="col" class="px-4 py-3">Default</th>
                            <th scope="col" class="px-4 py-3">Rates and effectiveness</th>
                            <th scope="col" class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($taxCategories as $taxCategory)
                            <tr wire:key="tax-category-{{ $taxCategory->id }}" class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3.5"><div class="font-semibold text-slate-900 dark:text-white">{{ $taxCategory->name }}</div><div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ number_format($taxCategory->rates_count) }} {{ \Illuminate\Support\Str::plural('rate', $taxCategory->rates_count) }}</div></td>
                                <td class="px-4 py-3.5"><span class="rounded bg-slate-100 px-2 py-1 font-mono text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ $taxCategory->code }}</span></td>
                                <td class="px-4 py-3.5">
                                    @if ($taxCategory->is_default)
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"><svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"/></svg>Default</span>
                                    @else
                                        <span class="text-sm text-slate-400 dark:text-slate-500">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5">
                                    @forelse ($taxCategory->rates as $rate)
                                        @php
                                            $rateValue = rtrim(rtrim(number_format((float) $rate->rate, 4, '.', ''), '0'), '.') ?: '0';
                                            $rateIsFuture = $rate->effective_from?->isFuture();
                                            $rateIsExpired = $rate->effective_to?->isPast() && ! $rate->effective_to?->isToday();
                                        @endphp
                                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1 py-1 {{ ! $loop->first ? 'border-t border-slate-100 dark:border-slate-800' : '' }}">
                                            <span class="min-w-0 font-medium text-slate-800 dark:text-slate-200">{{ $loop->iteration }}. {{ $rate->name }}</span>
                                            <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-semibold tabular-nums text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $rateValue }}%</span>
                                            @if ($rateIsFuture)
                                                <span class="text-xs text-sky-700 dark:text-sky-300">Starts {{ $rate->effective_from->format('d M Y') }}</span>
                                            @elseif ($rateIsExpired)
                                                <span class="text-xs text-slate-500 dark:text-slate-400">Ended {{ $rate->effective_to->format('d M Y') }}</span>
                                            @else
                                                <span class="text-xs font-medium text-emerald-700 dark:text-emerald-400">Effective</span>
                                            @endif
                                        </div>
                                    @empty
                                        <span class="text-sm text-amber-700 dark:text-amber-300">No rates configured</span>
                                    @endforelse
                                </td>
                                <td class="px-4 py-3.5 text-right">
                                    <div class="inline-flex items-center gap-1">
                                        @can('update', $taxCategory)
                                            <a href="{{ route('tax-categories.edit', $taxCategory) }}" aria-label="Edit {{ $taxCategory->name }}" title="Edit tax category" class="grid h-9 w-9 place-items-center rounded-md text-slate-500 transition hover:bg-slate-100 hover:text-emerald-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-emerald-300"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m16 4 4 4M4 20l4-.8L19 8a2.1 2.1 0 0 0-3-3L5 16l-1 4z"/></svg></a>
                                        @endcan
                                        @can('delete', $taxCategory)
                                            <button type="button" wire:click="delete({{ $taxCategory->id }})" wire:confirm="Delete tax category {{ $taxCategory->name }}?" aria-label="Delete {{ $taxCategory->name }}" title="Delete tax category" class="grid h-9 w-9 place-items-center rounded-md text-slate-500 transition hover:bg-rose-50 hover:text-rose-700 dark:text-slate-400 dark:hover:bg-rose-950/40 dark:hover:text-rose-300"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16m-10 4v6m4-6v6M5 7l1 14h12l1-14M9 7V4h6v3"/></svg></button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-14 text-center">
                                    <div class="mx-auto grid h-11 w-11 place-items-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v14H4zM8 9h8m-8 4h5"/></svg></div>
                                    <p class="mt-3 text-sm font-semibold text-slate-900 dark:text-white">{{ $search !== '' ? 'No tax categories match your search' : 'No tax categories configured' }}</p>
                                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $search !== '' ? 'Try another category, code, or rate name.' : 'Create a category to define the tax rules used for sales.' }}</p>
                                    @can('create', \App\Domain\Taxation\Models\TaxCategory::class)
                                        @if ($search === '')<a href="{{ route('tax-categories.create') }}" class="mt-4 inline-flex h-9 items-center rounded-md border border-slate-300 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Add first category</a>@endif
                                    @endcan
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($taxCategories->hasPages())
                <div class="flex flex-col gap-3 border-t border-slate-200 px-4 py-3 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-slate-500 dark:text-slate-400">Showing {{ number_format($taxCategories->firstItem()) }}–{{ number_format($taxCategories->lastItem()) }} of {{ number_format($taxCategories->total()) }}</p>
                    {{ $taxCategories->links() }}
                </div>
            @endif
        </section>
    </div>
</div>
