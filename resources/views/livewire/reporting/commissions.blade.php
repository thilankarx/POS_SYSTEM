<div>
    @section('title', 'Staff commission & tips report')
    @include('livewire.reporting._page-heading', ['description' => 'Review staff commission and tip totals with sale-level detail.'])

    <div class="mb-5 flex flex-wrap items-end gap-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
        <div>
            <label for="from" class="mb-1 block text-sm font-medium">From</label>
            <input wire:model.live.debounce.300ms="from" id="from" type="date" max="{{ $to }}" class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm focus:border-emerald-600 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
        </div>
        <div>
            <label for="to" class="mb-1 block text-sm font-medium">To</label>
            <input wire:model.live.debounce.300ms="to" id="to" type="date" min="{{ $from }}" class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm focus:border-emerald-600 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
        </div>
        <a href="{{ route('reports.commissions.export', ['from' => $from, 'to' => $to]) }}" class="inline-flex h-10 items-center rounded-md bg-emerald-700 px-4 text-sm font-semibold text-white hover:bg-emerald-800 dark:bg-emerald-600 dark:hover:bg-emerald-500">
            Export CSV
        </a>
    </div>

    <p class="rounded-md border-l-4 border-emerald-500 bg-emerald-50/70 px-4 py-3 text-sm leading-6 text-slate-700 dark:bg-emerald-950/20 dark:text-slate-300">
        Commission and tips are both credited to whoever opened the order (the waiter), not necessarily whoever completed payment. A voided sale earns neither. A refund reverses commission automatically, but never claws back a tip.
    </p>

    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <table class="w-full min-w-[720px] text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-2">Waiter</th>
                    <th class="px-4 py-2 text-right">Sales</th>
                    <th class="px-4 py-2 text-right">Total commission</th>
                    <th class="px-4 py-2 text-right">Total tips</th>
                    <th class="px-4 py-2"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($waiters as $waiter)
                    <tr class="border-b border-slate-100 transition-colors hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/50">
                        <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ $waiter->name }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-slate-700 dark:text-slate-200">{{ number_format((int) $waiter->sale_count) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums text-slate-900 dark:text-white">{{ \App\Support\Money\Money::of($waiter->total_commission) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums text-emerald-700 dark:text-emerald-400">{{ \App\Support\Money\Money::of($waiter->total_tips) }}</td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" wire:click="toggle({{ $waiter->id }})" class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-white dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-900">
                                {{ $expandedWaiterId === $waiter->id ? 'Hide' : 'Details' }}
                            </button>
                        </td>
                    </tr>
                    @if ($expandedWaiterId === $waiter->id)
                        <tr class="border-b border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-800/50">
                            <td colspan="5" class="px-4 py-4">
                                <div class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">Sales in range</div>
                                <table class="w-full min-w-[560px] text-left text-sm">
                                    <thead class="border-b border-slate-200 text-xs font-semibold uppercase text-slate-500 dark:border-slate-700 dark:text-slate-400">
                                        <tr>
                                            <th class="py-1">Number</th>
                                            <th class="py-1">Date</th>
                                            <th class="py-1">Type</th>
                                            <th class="py-1 text-right">Commission</th>
                                            <th class="py-1 text-right">Tip</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($drillDown as $sale)
                                            <tr>
                                                <td class="py-1">{{ $sale->number }}</td>
                                                <td class="py-1">{{ $sale->sold_at->format('Y-m-d H:i') }}</td>
                                                <td class="py-1">{{ $sale->sale_type === 'return' ? 'Return' : ucfirst($sale->sale_type) }}</td>
                                                <td class="py-1 text-right">{{ $sale->commission_amount }}</td>
                                                <td class="py-1 text-right">{{ $sale->tip_amount }}</td>
                                            </tr>
                                        @empty
                                            <tr><td class="py-1 text-gray-500 dark:text-gray-400">No sales in range.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No commission or tips earned in this range.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
