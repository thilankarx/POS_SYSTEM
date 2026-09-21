<div>
    @section('title', 'Dashboard')

    @php
        $user = auth()->user();
        $firstName = str($user->name)->before(' ');
        $formatMoney = static fn ($money) => \App\Support\Money\Money::format($money);
        $hasSalesPanel = $user->can('sales.view');
        $hasPurchasingPanel = ! $hasSalesPanel && $user->can('purchasing.view');
        $hasInventoryPanel = ! $hasSalesPanel && ! $hasPurchasingPanel && $user->can('inventory.view');
        $canUseShifts = $user->canAny(['shifts.open', 'shifts.close', 'shifts.view_all']);
        $lowStockUrl = $user->can('purchasing.view')
            ? route('reorder-suggestions.index', $primaryLocation ? ['stock_location_id' => $primaryLocation->id] : [])
            : route('items.index');
        $hasQuickActions = $user->canAny(['purchasing.manage', 'receivings.manage', 'inventory.count', 'expenses.manage', 'customers.manage', 'items.manage']);
    @endphp

    <div class="space-y-6">
        <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-medium text-emerald-700 dark:text-emerald-400">{{ now()->format('l, j F Y') }}</p>
                <h2 class="mt-1 text-2xl font-bold text-slate-950 dark:text-white">Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ $firstName }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Here is what needs your attention today.</p>
            </div>

            <div class="flex flex-wrap gap-2">
                @can('shifts.open')
                    <a href="{{ route('shift.manage') }}" class="inline-flex h-10 items-center gap-2 rounded-md border border-slate-300 bg-white px-3.5 text-sm font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        {{ $openShift ? 'Manage shift' : 'Open shift' }}
                    </a>
                @endcan
                @can('sales.create')
                    <a href="{{ route('pos') }}" class="inline-flex h-10 items-center gap-2 rounded-md bg-emerald-600 px-3.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l3-8H6.4M7 13l-2 4h13M9 21h.01M17 21h.01" /></svg>
                        Open register
                    </a>
                @endcan
                @if ($user->can('kitchen.view') && ! $user->can('sales.create'))
                    <a href="{{ route('pos') }}" class="inline-flex h-10 items-center gap-2 rounded-md bg-emerald-600 px-3.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 3h16v18H4zM8 7h8M8 11h8M8 15h5" /></svg>
                        Kitchen display
                    </a>
                @endif
            </div>
        </section>

        @if ($todaySales || $canUseShifts || $user->can('inventory.view') || $purchaseSummary)
            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="grid grid-cols-2 divide-x divide-y divide-slate-200 dark:divide-slate-800 lg:grid-cols-4 lg:divide-y-0">
                    @if ($todaySales)
                        <a href="{{ route('reports.sales') }}" class="min-w-0 p-4 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60 sm:p-5">
                            <p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Sales today</p>
                            <p class="mt-2 truncate text-xl font-bold tabular-nums text-slate-950 dark:text-white sm:text-2xl">{{ $formatMoney($todaySales->total) }}</p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Across all locations</p>
                        </a>
                        <a href="{{ route('reports.sales') }}" class="min-w-0 p-4 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60 sm:p-5">
                            <p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Transactions</p>
                            <p class="mt-2 text-xl font-bold tabular-nums text-slate-950 dark:text-white sm:text-2xl">{{ number_format($todaySales->saleCount) }}</p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Completed today</p>
                        </a>
                    @elseif ($openShift)
                        <a href="{{ route('shift.manage') }}" class="min-w-0 p-4 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60 sm:p-5">
                            <p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Current shift</p>
                            <p class="mt-2 truncate text-base font-bold text-slate-950 dark:text-white sm:text-lg">{{ $openShift->terminal->name }}</p>
                            <p class="mt-1 text-xs text-emerald-700 dark:text-emerald-400">Open for {{ $openShift->opened_at->diffForHumans(short: true) }}</p>
                        </a>
                        <a href="{{ route('shift.manage') }}" class="min-w-0 p-4 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60 sm:p-5">
                            <p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Shift sales</p>
                            <p class="mt-2 truncate text-xl font-bold tabular-nums text-slate-950 dark:text-white sm:text-2xl">{{ $formatMoney($shiftSales->total) }}</p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ number_format($shiftSales->sale_count) }} transactions</p>
                        </a>
                    @elseif ($canUseShifts)
                        <a href="{{ route('shift.manage') }}" class="min-w-0 p-4 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60 sm:p-5">
                            <p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Shift</p>
                            <p class="mt-2 text-xl font-bold text-slate-950 dark:text-white sm:text-2xl">Closed</p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">No active shift</p>
                        </a>
                    @endif

                    @can('inventory.view')
                        <a href="{{ $lowStockUrl }}" class="min-w-0 p-4 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60 sm:p-5">
                            <p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Low stock</p>
                            <p class="mt-2 text-xl font-bold tabular-nums {{ $lowStockItems->isNotEmpty() ? 'text-amber-700 dark:text-amber-400' : 'text-slate-950 dark:text-white' }} sm:text-2xl">{{ number_format($lowStockItems->count()) }}</p>
                            <p class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400">{{ $primaryLocation?->name ?? 'No location assigned' }}</p>
                        </a>
                    @endcan

                    @if ($purchaseSummary)
                        <a href="{{ route('purchase-orders.index') }}" class="min-w-0 p-4 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60 sm:p-5">
                            <p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">To receive</p>
                            <p class="mt-2 text-xl font-bold tabular-nums {{ $purchaseSummary['to_receive'] > 0 ? 'text-blue-700 dark:text-blue-400' : 'text-slate-950 dark:text-white' }} sm:text-2xl">{{ number_format($purchaseSummary['to_receive']) }}</p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Approved orders</p>
                        </a>
                    @endif

                    @if ($invoiceSummary && ! $user->can('inventory.view'))
                        <a href="{{ route('supplier-invoices.index') }}" class="min-w-0 p-4 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60 sm:p-5">
                            <p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Outstanding</p>
                            <p class="mt-2 truncate text-xl font-bold tabular-nums text-slate-950 dark:text-white sm:text-2xl">{{ $formatMoney($invoiceSummary['outstanding']) }}</p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Supplier invoices</p>
                        </a>
                    @endif
                </div>
            </section>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:col-span-2">
                <div class="flex items-center justify-between border-b border-slate-200 px-4 py-3.5 dark:border-slate-800 sm:px-5">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-950 dark:text-white">{{ $hasSalesPanel ? 'Recent sales' : ($hasPurchasingPanel ? 'Orders ready to receive' : ($hasInventoryPanel ? 'Low-stock items' : 'Your workspace')) }}</h3>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $hasSalesPanel ? 'Latest completed transactions' : ($hasPurchasingPanel ? 'Approved and partially received orders' : ($hasInventoryPanel ? 'Items below their reorder level' : 'Frequently used areas')) }}</p>
                    </div>
                    @if ($hasSalesPanel)<a href="{{ route('sales.index') }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800 dark:text-emerald-400">View all</a>
                    @elseif ($hasPurchasingPanel)<a href="{{ route('purchase-orders.index') }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800 dark:text-emerald-400">View all</a>
                    @elseif ($hasInventoryPanel)<a href="{{ route('reorder-suggestions.index') }}" class="text-sm font-semibold text-emerald-700 hover:text-emerald-800 dark:text-emerald-400">View all</a>@endif
                </div>

                @if ($hasSalesPanel)
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-950/50 dark:text-slate-400"><tr><th class="px-4 py-2.5 font-semibold sm:px-5">Sale</th><th class="px-4 py-2.5 font-semibold">Customer</th><th class="px-4 py-2.5 font-semibold">Time</th><th class="px-4 py-2.5 text-right font-semibold sm:px-5">Total</th></tr></thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @forelse ($recentSales as $sale)
                                    <tr class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                        <td class="whitespace-nowrap px-4 py-3 font-semibold text-slate-900 dark:text-white sm:px-5"><a href="{{ route('sales.show', $sale) }}" class="hover:text-emerald-700 dark:hover:text-emerald-400">{{ $sale->number }}</a></td>
                                        <td class="max-w-48 truncate px-4 py-3 text-slate-600 dark:text-slate-300">{{ $sale->customer?->person?->full_name ?: 'Walk-in' }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-slate-500 dark:text-slate-400">{{ $sale->sold_at->isToday() ? $sale->sold_at->format('g:i A') : $sale->sold_at->format('d M, g:i A') }}</td>
                                        <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums text-slate-900 dark:text-white sm:px-5">{{ $formatMoney($sale->total) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-5 py-12 text-center text-sm text-slate-500 dark:text-slate-400">No completed sales yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @elseif ($hasPurchasingPanel)
                    <div class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($purchaseOrders as $order)
                            <a href="{{ route('purchase-orders.edit', $order) }}" class="flex items-center gap-3 px-4 py-3.5 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50 sm:px-5">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-md bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300"><svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8 4-8-4m16 0l-8-4-8 4m16 0v10l-8 4m0-10L4 7m8 4v10" /></svg></span>
                                <span class="min-w-0 flex-1"><span class="block truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $order->number }} · {{ $order->supplier->company_name }}</span><span class="mt-0.5 block truncate text-xs text-slate-500 dark:text-slate-400">{{ $order->stockLocation->name }} · {{ str($order->status)->replace('_', ' ')->title() }}</span></span>
                                <span class="shrink-0 text-right"><span class="block text-sm font-semibold tabular-nums text-slate-900 dark:text-white">{{ $formatMoney($order->total) }}</span><span class="mt-0.5 block text-xs {{ $order->expected_on?->isPast() ? 'text-red-600 dark:text-red-400' : 'text-slate-500 dark:text-slate-400' }}">{{ $order->expected_on?->format('d M') ?? 'Unscheduled' }}</span></span>
                            </a>
                        @empty
                            <div class="px-5 py-12 text-center text-sm text-slate-500 dark:text-slate-400">No orders are waiting to be received.</div>
                        @endforelse
                    </div>
                @elseif ($hasInventoryPanel)
                    <div class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($lowStockItems->take(6) as $item)
                            <a href="{{ route('items.edit', $item) }}" class="flex items-center justify-between gap-4 px-4 py-3.5 transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50 sm:px-5">
                                <span class="min-w-0"><span class="block truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $item->name }}</span><span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">{{ $item->sku }} · {{ $item->supplier?->company_name ?? 'No supplier' }}</span></span>
                                <span class="shrink-0 text-right"><span class="block text-sm font-semibold tabular-nums text-amber-700 dark:text-amber-400">{{ (float) $item->on_hand }} on hand</span><span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">Reorder at {{ (float) $item->reorder_level }}</span></span>
                            </a>
                        @empty
                            <div class="px-5 py-12 text-center text-sm text-slate-500 dark:text-slate-400">Stock levels look healthy at this location.</div>
                        @endforelse
                    </div>
                @else
                    <div class="grid gap-3 p-4 sm:grid-cols-2 sm:p-5">
                        @can('reports.sales')<a href="{{ route('reports.sales') }}" class="rounded-md border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:border-emerald-400 hover:text-emerald-700 dark:border-slate-700 dark:text-slate-200">Sales report</a>@endcan
                        @can('reports.inventory')<a href="{{ route('reports.inventory') }}" class="rounded-md border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:border-emerald-400 hover:text-emerald-700 dark:border-slate-700 dark:text-slate-200">Inventory report</a>@endcan
                        @can('customers.view')<a href="{{ route('customers.index') }}" class="rounded-md border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:border-emerald-400 hover:text-emerald-700 dark:border-slate-700 dark:text-slate-200">Customers</a>@endcan
                        @can('items.view')<a href="{{ route('items.index') }}" class="rounded-md border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:border-emerald-400 hover:text-emerald-700 dark:border-slate-700 dark:text-slate-200">Items</a>@endcan
                        @if ($user->can('kitchen.view'))<a href="{{ route('pos') }}" class="rounded-md border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 hover:border-emerald-400 hover:text-emerald-700 dark:border-slate-700 dark:text-slate-200">Kitchen display</a>@endif
                    </div>
                @endif
            </section>

            <div class="space-y-6">
                <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-4 py-3.5 dark:border-slate-800"><h3 class="text-sm font-semibold text-slate-950 dark:text-white">Needs attention</h3></div>
                    <div class="divide-y divide-slate-100 dark:divide-slate-800">
                        @php $attentionCount = 0; @endphp
                        @if ($purchaseSummary && $user->can('purchasing.approve') && $purchaseSummary['awaiting_approval'] > 0)
                            @php $attentionCount++; @endphp
                            <a href="{{ route('purchase-orders.index', ['status' => 'submitted']) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800/50"><span class="h-2 w-2 shrink-0 rounded-full bg-amber-500"></span><span class="min-w-0 flex-1 text-sm text-slate-700 dark:text-slate-300">Orders awaiting approval</span><span class="rounded bg-amber-50 px-2 py-0.5 text-xs font-bold tabular-nums text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">{{ $purchaseSummary['awaiting_approval'] }}</span></a>
                        @endif
                        @if ($purchaseSummary && $purchaseSummary['overdue'] > 0)
                            @php $attentionCount++; @endphp
                            <a href="{{ route('purchase-orders.index', ['timing' => 'overdue']) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800/50"><span class="h-2 w-2 shrink-0 rounded-full bg-red-500"></span><span class="min-w-0 flex-1 text-sm text-slate-700 dark:text-slate-300">Overdue purchase orders</span><span class="rounded bg-red-50 px-2 py-0.5 text-xs font-bold tabular-nums text-red-700 dark:bg-red-500/10 dark:text-red-300">{{ $purchaseSummary['overdue'] }}</span></a>
                        @endif
                        @if ($invoiceSummary && $invoiceSummary['overdue'] > 0)
                            @php $attentionCount++; @endphp
                            <a href="{{ route('supplier-invoices.index', ['timing' => 'overdue']) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800/50"><span class="h-2 w-2 shrink-0 rounded-full bg-red-500"></span><span class="min-w-0 flex-1 text-sm text-slate-700 dark:text-slate-300">Overdue supplier invoices</span><span class="rounded bg-red-50 px-2 py-0.5 text-xs font-bold tabular-nums text-red-700 dark:bg-red-500/10 dark:text-red-300">{{ $invoiceSummary['overdue'] }}</span></a>
                        @endif
                        @if ($invoiceSummary && $invoiceSummary['disputed'] > 0)
                            @php $attentionCount++; @endphp
                            <a href="{{ route('supplier-invoices.index', ['status' => 'disputed']) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800/50"><span class="h-2 w-2 shrink-0 rounded-full bg-violet-500"></span><span class="min-w-0 flex-1 text-sm text-slate-700 dark:text-slate-300">Invoice discrepancies</span><span class="rounded bg-violet-50 px-2 py-0.5 text-xs font-bold tabular-nums text-violet-700 dark:bg-violet-500/10 dark:text-violet-300">{{ $invoiceSummary['disputed'] }}</span></a>
                        @endif
                        @if ($user->can('inventory.view') && $lowStockItems->isNotEmpty())
                            @php $attentionCount++; @endphp
                            <a href="{{ $lowStockUrl }}" class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-800/50"><span class="h-2 w-2 shrink-0 rounded-full bg-amber-500"></span><span class="min-w-0 flex-1 text-sm text-slate-700 dark:text-slate-300">Items below reorder level</span><span class="rounded bg-amber-50 px-2 py-0.5 text-xs font-bold tabular-nums text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">{{ $lowStockItems->count() }}</span></a>
                        @endif
                        @if ($attentionCount === 0)
                            <div class="px-4 py-8 text-center"><span class="mx-auto grid h-9 w-9 place-items-center rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg></span><p class="mt-2 text-sm font-medium text-slate-700 dark:text-slate-300">Nothing urgent right now</p></div>
                        @endif
                    </div>
                </section>

                @if ($hasQuickActions)
                <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <h3 class="text-sm font-semibold text-slate-950 dark:text-white">Quick actions</h3>
                    <div class="mt-3 grid grid-cols-2 gap-2">
                        @can('purchasing.manage')<a href="{{ route('purchase-orders.create') }}" class="rounded-md border border-slate-200 px-3 py-2.5 text-sm font-medium text-slate-700 hover:border-emerald-400 hover:text-emerald-700 dark:border-slate-700 dark:text-slate-200">New order</a>@endcan
                        @can('receivings.manage')<a href="{{ route('receivings.create') }}" class="rounded-md border border-slate-200 px-3 py-2.5 text-sm font-medium text-slate-700 hover:border-emerald-400 hover:text-emerald-700 dark:border-slate-700 dark:text-slate-200">Receive stock</a>@endcan
                        @can('inventory.count')<a href="{{ route('stock-counts.create') }}" class="rounded-md border border-slate-200 px-3 py-2.5 text-sm font-medium text-slate-700 hover:border-emerald-400 hover:text-emerald-700 dark:border-slate-700 dark:text-slate-200">Stock count</a>@endcan
                        @can('expenses.manage')<a href="{{ route('expenses.create') }}" class="rounded-md border border-slate-200 px-3 py-2.5 text-sm font-medium text-slate-700 hover:border-emerald-400 hover:text-emerald-700 dark:border-slate-700 dark:text-slate-200">New expense</a>@endcan
                        @can('customers.manage')<a href="{{ route('customers.create') }}" class="rounded-md border border-slate-200 px-3 py-2.5 text-sm font-medium text-slate-700 hover:border-emerald-400 hover:text-emerald-700 dark:border-slate-700 dark:text-slate-200">New customer</a>@endcan
                        @can('items.manage')<a href="{{ route('items.create') }}" class="rounded-md border border-slate-200 px-3 py-2.5 text-sm font-medium text-slate-700 hover:border-emerald-400 hover:text-emerald-700 dark:border-slate-700 dark:text-slate-200">New item</a>@endcan
                    </div>
                </section>
                @endif
            </div>
        </div>
    </div>
</div>
