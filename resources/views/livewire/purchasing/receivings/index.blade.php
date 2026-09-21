<div>
    @section('title', 'Receivings')

    <div class="mx-auto max-w-7xl space-y-5">
        @if (session('status'))
            <div role="status" class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">{{ session('status') }}</div>
        @endif

        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-400">Purchasing</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-950 dark:text-white">Receivings</h1>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Review goods receipts, supplier returns, and stock transfers.</p>
            </div>
            @can('create', \App\Domain\Purchasing\Models\Receiving::class)
                <a href="{{ route('receivings.create') }}" class="inline-flex h-10 items-center justify-center gap-2 self-start rounded-md bg-emerald-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 dark:bg-emerald-600 dark:hover:bg-emerald-500 sm:self-auto">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-7-7h14" /></svg>
                    New receiving
                </a>
            @endcan
        </header>

        <section aria-label="Receiving totals" class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <dl class="grid grid-cols-3 divide-x divide-slate-200 dark:divide-slate-800">
                <div class="p-3 sm:p-4"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">All receivings</dt><dd class="mt-1.5 text-xl font-bold tabular-nums text-slate-950 dark:text-white">{{ number_format($totalReceivings) }}</dd></div>
                <div class="p-3 sm:p-4"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">This month</dt><dd class="mt-1.5 text-xl font-bold tabular-nums text-emerald-700 dark:text-emerald-400">{{ number_format($thisMonthReceivings) }}</dd></div>
                <div class="p-3 sm:p-4"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">From purchase orders</dt><dd class="mt-1.5 text-xl font-bold tabular-nums text-slate-950 dark:text-white">{{ number_format($purchaseOrderReceivings) }}</dd></div>
            </dl>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-3 border-b border-slate-200 p-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-slate-950 dark:text-white">Receiving records</h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ number_format($receivings->total()) }} {{ \Illuminate\Support\Str::plural('record', $receivings->total()) }}, newest first</p>
                </div>
                <div class="relative w-full sm:max-w-sm">
                    <label for="receiving-search" class="sr-only">Search receivings</label>
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m16 16 4 4"/></svg>
                    <input wire:model.live.debounce.300ms="search" id="receiving-search" type="search" autocomplete="off" placeholder="Number, supplier, or PO reference" class="h-10 w-full rounded-md border border-slate-300 bg-white pl-9 pr-10 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    @if ($search !== '')
                        <button type="button" wire:click="$set('search', '')" aria-label="Clear search" title="Clear search" class="absolute right-2 top-1/2 grid h-7 w-7 -translate-y-1/2 place-items-center rounded text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-white"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg></button>
                    @endif
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1080px] text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-400">
                        <tr>
                            <th scope="col" class="px-4 py-3">Receiving</th>
                            <th scope="col" class="px-4 py-3">Supplier</th>
                            <th scope="col" class="px-4 py-3">Purchase order</th>
                            <th scope="col" class="px-4 py-3">Type</th>
                            <th scope="col" class="px-4 py-3">Location</th>
                            <th scope="col" class="px-4 py-3 text-right">Lines</th>
                            <th scope="col" class="px-4 py-3 text-right">Total</th>
                            <th scope="col" class="px-4 py-3">Received</th>
                            <th scope="col" class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($receivings as $receiving)
                            @php
                                [$typeLabel, $typeStyle] = match ($receiving->type) {
                                    'receipt' => ['Goods receipt', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'],
                                    'return_to_supplier' => ['Supplier return', 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300'],
                                    'transfer_in' => ['Transfer in', 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300'],
                                    'transfer_out' => ['Transfer out', 'bg-amber-50 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300'],
                                    default => [ucfirst(str_replace('_', ' ', $receiving->type)), 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'],
                                };
                            @endphp
                            <tr wire:key="receiving-{{ $receiving->id }}" class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3.5"><div class="font-semibold text-slate-900 dark:text-white">{{ $receiving->number }}</div><div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $receiving->user?->name ?? 'Unknown user' }}</div></td>
                                <td class="px-4 py-3.5"><div class="font-medium text-slate-800 dark:text-slate-200">{{ $receiving->supplier?->company_name ?? '—' }}</div>@if ($receiving->supplier_reference)<div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Ref: {{ $receiving->supplier_reference }}</div>@endif</td>
                                <td class="px-4 py-3.5">
                                    @if ($receiving->purchaseOrder)
                                        @can('purchasing.view')<a href="{{ route('purchase-orders.edit', $receiving->purchaseOrder) }}" class="font-medium text-emerald-700 hover:underline dark:text-emerald-400">{{ $receiving->purchaseOrder->number }}</a>@else<span class="text-slate-700 dark:text-slate-200">{{ $receiving->purchaseOrder->number }}</span>@endcan
                                    @else
                                        <span class="text-slate-400 dark:text-slate-500">Standalone</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $typeStyle }}">{{ $typeLabel }}</span></td>
                                <td class="px-4 py-3.5 text-slate-600 dark:text-slate-300"><div>{{ $receiving->stockLocation?->name ?? '—' }}</div>@if ($receiving->transferToLocation)<div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">To: {{ $receiving->transferToLocation->name }}</div>@endif</td>
                                <td class="px-4 py-3.5 text-right tabular-nums text-slate-600 dark:text-slate-300">{{ number_format((int) $receiving->lines_count) }}</td>
                                <td class="whitespace-nowrap px-4 py-3.5 text-right font-semibold tabular-nums text-slate-900 dark:text-white">{{ $receiving->total }}</td>
                                <td class="whitespace-nowrap px-4 py-3.5"><div class="font-medium text-slate-800 dark:text-slate-200">{{ $receiving->received_at->format('d M Y') }}</div><div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $receiving->received_at->format('H:i') }}</div></td>
                                <td class="px-4 py-3.5 text-right">
                                    <div class="inline-flex items-center gap-1">
                                        @can('view', $receiving)
                                            <a href="{{ route('receivings.show', $receiving) }}" aria-label="View receiving {{ $receiving->number }}" title="View receiving" class="grid h-9 w-9 place-items-center rounded-md text-slate-500 transition hover:bg-slate-100 hover:text-emerald-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-emerald-300"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.5 12s3.3-7 9.5-7 9.5 7 9.5 7-3.3 7-9.5 7-9.5-7-9.5-7z"/><circle cx="12" cy="12" r="3"/></svg></a>
                                        @endcan
                                        @can('receivings.manage')
                                            <a href="{{ route('receivings.labels', $receiving) }}" aria-label="Print labels for {{ $receiving->number }}" title="Print labels" class="grid h-9 w-9 place-items-center rounded-md text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 8V3h10v5M7 17H5a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2m-10-4h10v8H7z"/></svg></a>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-5 py-14 text-center">
                                    <div class="mx-auto grid h-11 w-11 place-items-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v14H4zM8 9h8m-8 4h8m-8 3h4"/></svg></div>
                                    <p class="mt-3 text-sm font-semibold text-slate-900 dark:text-white">{{ $search !== '' ? 'No receivings match your search' : 'No receiving records yet' }}</p>
                                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $search !== '' ? 'Try a receiving number, supplier name, or purchase order.' : 'Receipts and stock movements will appear here.' }}</p>
                                    @can('create', \App\Domain\Purchasing\Models\Receiving::class)
                                        @if ($search === '')<a href="{{ route('receivings.create') }}" class="mt-4 inline-flex h-9 items-center rounded-md border border-slate-300 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Create receiving</a>@endif
                                    @endcan
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($receivings->hasPages())
                <div class="flex flex-col gap-3 border-t border-slate-200 px-4 py-3 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-slate-500 dark:text-slate-400">Showing {{ number_format($receivings->firstItem()) }}–{{ number_format($receivings->lastItem()) }} of {{ number_format($receivings->total()) }}</p>
                    {{ $receivings->links() }}
                </div>
            @endif
        </section>
    </div>
</div>
