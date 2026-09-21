<div>
    @section('title', 'Suppliers')

    <div class="mx-auto max-w-7xl space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-medium text-emerald-700 dark:text-emerald-400">Purchasing</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 dark:text-white">Suppliers</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Manage supplier contacts, lead times, and catalog relationships.</p>
            </div>
            @can('create', \App\Domain\Crm\Models\Supplier::class)
                <a href="{{ route('suppliers.create') }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-emerald-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 dark:bg-emerald-600 dark:hover:bg-emerald-500">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
                    New supplier
                </a>
            @endcan
        </header>

        @if (session('status'))
            <div role="status" class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">{{ session('status') }}</div>
        @endif

        <section aria-label="Supplier summary" class="grid grid-cols-1 divide-y divide-slate-200 rounded-lg border border-slate-200 bg-white sm:grid-cols-3 sm:divide-x sm:divide-y-0 dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
            <div class="px-5 py-4"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">All suppliers</p><p class="mt-1 text-2xl font-bold tabular-nums text-slate-950 dark:text-white">{{ number_format($stats['total']) }}</p></div>
            <div class="px-5 py-4"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Goods suppliers</p><p class="mt-1 text-2xl font-bold tabular-nums text-emerald-700 dark:text-emerald-300">{{ number_format($stats['goods']) }}</p></div>
            <div class="px-5 py-4"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Expense suppliers</p><p class="mt-1 text-2xl font-bold tabular-nums text-sky-700 dark:text-sky-300">{{ number_format($stats['expense']) }}</p></div>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                <label class="relative block sm:w-[26rem]">
                    <span class="sr-only">Search suppliers</span>
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7" /><path stroke-linecap="round" d="m20 20-4-4" /></svg>
                    <input wire:model.live.debounce.300ms="search" type="search" placeholder="Company, contact, email, phone, or account" class="h-10 w-full rounded-md border border-slate-300 bg-white pl-9 pr-3 text-sm text-slate-950 outline-none transition placeholder:text-slate-400 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                </label>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $suppliers->total() }} {{ \Illuminate\Support\Str::plural('supplier', $suppliers->total()) }}</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500 dark:bg-slate-950/60 dark:text-slate-400">
                        <tr>
                            <th scope="col" class="px-4 py-3 sm:px-5">Supplier</th>
                            <th scope="col" class="px-4 py-3">Contact</th>
                            <th scope="col" class="px-4 py-3">Type</th>
                            <th scope="col" class="px-4 py-3">Lead time</th>
                            <th scope="col" class="px-4 py-3">Catalog</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($suppliers as $supplier)
                            <tr class="transition hover:bg-slate-50/70 dark:hover:bg-slate-800/30">
                                <td class="px-4 py-3.5 sm:px-5">
                                    <div class="flex min-w-52 items-center gap-3">
                                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-md bg-slate-100 text-sm font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ str($supplier->company_name)->substr(0, 1)->upper() }}</span>
                                        <span class="min-w-0"><span class="block truncate font-semibold text-slate-900 dark:text-white">{{ $supplier->company_name }}</span>
                                            @if ($supplier->agency_name)<span class="mt-0.5 block truncate text-xs text-slate-500 dark:text-slate-400">{{ $supplier->agency_name }}</span>@endif
                                            @if ($supplier->account_number)<span class="mt-0.5 block truncate font-mono text-xs text-slate-500 dark:text-slate-400">{{ $supplier->account_number }}</span>@endif
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="block font-medium text-slate-800 dark:text-slate-200">{{ $supplier->person?->full_name ?? 'No contact name' }}</span>
                                    <span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">{{ $supplier->person?->email ?: ($supplier->person?->phone ?: 'No contact details') }}</span>
                                </td>
                                <td class="px-4 py-3.5"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $supplier->supplier_type === 'goods' ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-sky-50 text-sky-800 dark:bg-sky-500/10 dark:text-sky-300' }}">{{ str($supplier->supplier_type)->replace('_', ' ')->headline() }}</span></td>
                                <td class="px-4 py-3.5 text-sm tabular-nums text-slate-700 dark:text-slate-300">{{ number_format($supplier->lead_time_days) }} <span class="text-xs text-slate-500">{{ \Illuminate\Support\Str::plural('day', $supplier->lead_time_days) }}</span></td>
                                <td class="px-4 py-3.5 text-sm tabular-nums text-slate-700 dark:text-slate-300">{{ number_format($supplier->items_count) }} <span class="text-xs text-slate-500">{{ \Illuminate\Support\Str::plural('item', $supplier->items_count) }}</span></td>
                                <td class="whitespace-nowrap px-4 py-3.5 text-right">
                                    @can('update', $supplier)
                                        <a href="{{ route('suppliers.edit', $supplier) }}" class="inline-flex h-9 items-center rounded-md border border-slate-300 px-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Manage</a>
                                    @endcan
                                    @can('delete', $supplier)
                                        <button type="button" wire:click="delete({{ $supplier->id }})" wire:confirm="Delete '{{ $supplier->company_name }}'?" aria-label="Delete {{ $supplier->company_name }}" title="Delete supplier" class="ml-1 inline-grid h-9 w-9 place-items-center rounded-md text-slate-400 transition hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-950/40 dark:hover:text-rose-300"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12m-10 0l1 13h6l1-13M9 7V4h6v3" /></svg></button>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-14 text-center">
                                <div class="mx-auto grid h-11 w-11 place-items-center rounded-md bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8h18v12H3zM7 8V5h10v3M7 12h10" /></svg></div>
                                <p class="mt-3 text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $search !== '' ? 'No matching suppliers' : 'No suppliers yet' }}</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $search !== '' ? 'Try another company, contact, or account search.' : 'Add suppliers to organize purchasing contacts and item relationships.' }}</p>
                                @if ($search === '')
                                    @can('create', \App\Domain\Crm\Models\Supplier::class)
                                        <a href="{{ route('suppliers.create') }}" class="mt-4 inline-flex h-9 items-center gap-1.5 rounded-md bg-emerald-700 px-3.5 text-sm font-semibold text-white hover:bg-emerald-800 dark:bg-emerald-600 dark:hover:bg-emerald-500"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>Add supplier</a>
                                    @endcan
                                @endif
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($suppliers->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $suppliers->links() }}</div>
            @endif
        </section>
    </div>
</div>
