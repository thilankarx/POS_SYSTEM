<div>
    @section('title', 'Purchase Orders')

    @php
        $formatQuantity = static fn ($quantity) => rtrim(rtrim((string) $quantity, '0'), '.');
        $statusStyles = [
            'draft' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-200',
            'submitted' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
            'approved' => 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200',
            'partially_received' => 'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-200',
            'received' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
            'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200',
        ];
        $hasFilters = $search !== '' || $status !== '' || $supplier !== '' || $location !== '' || $timing !== '' || $sort !== 'newest';
    @endphp

    <div class="space-y-4">
        <div class="flex flex-col gap-3 border-b border-gray-200 pb-4 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold">Purchase orders</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $summary['total'] }} total orders</p>
            </div>
            @can('create', \App\Domain\Purchasing\Models\PurchaseOrder::class)
                <a href="{{ route('purchase-orders.create') }}" class="self-start rounded-md bg-[#1b1b18] px-4 py-2 text-sm font-medium text-white hover:bg-black dark:bg-white dark:text-[#1b1b18]">New purchase order</a>
            @endcan
        </div>

        <section class="grid grid-cols-2 gap-px overflow-hidden rounded-lg border border-gray-200 bg-gray-200 shadow-sm dark:border-gray-700 dark:bg-gray-700 lg:grid-cols-4">
            <div class="bg-white px-4 py-3 dark:bg-gray-900">
                <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">All orders</div>
                <div class="mt-1 text-xl font-semibold">{{ $summary['total'] }}</div>
            </div>
            <div class="bg-white px-4 py-3 dark:bg-gray-900">
                <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Awaiting approval</div>
                <div class="mt-1 text-xl font-semibold text-amber-700 dark:text-amber-300">{{ $summary['awaitingApproval'] }}</div>
            </div>
            <div class="bg-white px-4 py-3 dark:bg-gray-900">
                <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">To receive</div>
                <div class="mt-1 text-xl font-semibold text-blue-700 dark:text-blue-300">{{ $summary['toReceive'] }}</div>
            </div>
            <div class="bg-white px-4 py-3 dark:bg-gray-900">
                <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Overdue</div>
                <div class="mt-1 text-xl font-semibold {{ $summary['overdue'] > 0 ? 'text-red-700 dark:text-red-300' : '' }}">{{ $summary['overdue'] }}</div>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-6">
                <div class="sm:col-span-2 lg:col-span-2">
                    <label for="po_search" class="sr-only">Search purchase orders</label>
                    <input wire:model.live.debounce.300ms="search" id="po_search" type="search" placeholder="Search number, supplier, or note" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                </div>
                <div>
                    <label for="po_status" class="sr-only">Status</label>
                    <select wire:model.live="status" id="po_status" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                        <option value="">All statuses</option>
                        <option value="draft">Draft</option>
                        <option value="submitted">Submitted</option>
                        <option value="approved">Approved</option>
                        <option value="partially_received">Partially received</option>
                        <option value="received">Received</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div>
                    <label for="po_supplier" class="sr-only">Supplier</label>
                    <select wire:model.live="supplier" id="po_supplier" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                        <option value="">All suppliers</option>
                        @foreach ($suppliers as $supplierOption)
                            <option value="{{ $supplierOption->id }}">{{ $supplierOption->company_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="po_location" class="sr-only">Location</label>
                    <select wire:model.live="location" id="po_location" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                        <option value="">All locations</option>
                        @foreach ($stockLocations as $locationOption)
                            <option value="{{ $locationOption->id }}">{{ $locationOption->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="po_timing" class="sr-only">Delivery timing</label>
                    <select wire:model.live="timing" id="po_timing" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                        <option value="">Any delivery date</option>
                        <option value="overdue">Overdue</option>
                        <option value="due_soon">Due in 7 days</option>
                        <option value="unscheduled">No expected date</option>
                    </select>
                </div>
            </div>
            <div class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-3 dark:border-gray-800">
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    Showing {{ $purchaseOrders->firstItem() ?? 0 }}-{{ $purchaseOrders->lastItem() ?? 0 }} of {{ $purchaseOrders->total() }}
                </div>
                <div class="flex items-center gap-2">
                    <label for="po_sort" class="text-sm text-gray-500 dark:text-gray-400">Sort</label>
                    <select wire:model.live="sort" id="po_sort" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-800">
                        <option value="newest">Newest first</option>
                        <option value="expected">Expected date</option>
                        <option value="total_desc">Highest total</option>
                    </select>
                    @if ($hasFilters)
                        <button type="button" wire:click="clearFilters" class="px-2 py-1.5 text-sm text-gray-600 hover:underline dark:text-gray-300">Clear filters</button>
                    @endif
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900" wire:loading.class="opacity-60" wire:target="search,status,supplier,location,timing,sort,clearFilters">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1050px] text-left text-sm">
                    <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500 dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3">Order</th>
                            <th class="px-4 py-3">Supplier</th>
                            <th class="px-4 py-3">Deliver to</th>
                            <th class="px-4 py-3">Expected</th>
                            <th class="px-4 py-3">Fulfilment</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3 text-center">Receipts</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($purchaseOrders as $purchaseOrder)
                            @php
                                $ordered = '0.000';
                                $received = '0.000';
                                foreach ($purchaseOrder->lines as $line) {
                                    $ordered = bcadd($ordered, (string) $line->quantity_ordered, 3);
                                    $received = bcadd($received, (string) $line->quantity_received, 3);
                                }
                                $fulfilment = bccomp($ordered, '0', 3) > 0
                                    ? min(100, (int) round(((float) $received / (float) $ordered) * 100))
                                    : 0;
                                $isOpen = in_array($purchaseOrder->status, ['draft', 'submitted', 'approved', 'partially_received'], true);
                                $isOverdue = $isOpen && $purchaseOrder->expected_on?->isBefore(today());
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                <td class="px-4 py-3">
                                    <a href="{{ route('purchase-orders.edit', $purchaseOrder) }}" class="font-semibold hover:underline">{{ $purchaseOrder->number }}</a>
                                    <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Created {{ $purchaseOrder->created_at->format('Y-m-d') }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ $purchaseOrder->supplier?->company_name ?? 'Not set' }}</div>
                                    <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $purchaseOrder->lines->count() }} {{ \Illuminate\Support\Str::plural('line', $purchaseOrder->lines->count()) }}</div>
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $purchaseOrder->stockLocation?->name ?? 'Not set' }}</td>
                                <td class="px-4 py-3">
                                    <div class="font-medium {{ $isOverdue ? 'text-red-700 dark:text-red-300' : '' }}">{{ $purchaseOrder->expected_on?->format('Y-m-d') ?? 'Not scheduled' }}</div>
                                    @if ($isOverdue)<div class="mt-0.5 text-xs font-medium text-red-600 dark:text-red-400">Overdue</div>@endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="h-1.5 w-24 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"><div class="h-full bg-emerald-600" style="width: {{ $fulfilment }}%"></div></div>
                                        <span class="text-xs font-medium">{{ $fulfilment }}%</span>
                                    </div>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $formatQuantity($received) }} / {{ $formatQuantity($ordered) }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $statusStyles[$purchaseOrder->status] ?? $statusStyles['draft'] }}">{{ str_replace('_', ' ', $purchaseOrder->status) }}</span>
                                </td>
                                <td class="px-4 py-3 text-center tabular-nums">{{ $purchaseOrder->receivings_count }}</td>
                                <td class="px-4 py-3 text-right font-medium tabular-nums">{{ $purchaseOrder->total }}</td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        @if (in_array($purchaseOrder->status, ['approved', 'partially_received'], true) && \Illuminate\Support\Facades\Route::has('receivings.create'))
                                            @can('create', \App\Domain\Purchasing\Models\Receiving::class)
                                                <a href="{{ route('receivings.create', ['purchase_order' => $purchaseOrder->id]) }}" class="font-medium text-emerald-700 hover:underline dark:text-emerald-400">Receive</a>
                                            @endcan
                                        @endif
                                        <a href="{{ route('purchase-orders.edit', $purchaseOrder) }}" class="font-medium text-gray-700 hover:underline dark:text-gray-300">{{ $purchaseOrder->status === 'draft' ? 'Edit' : 'View' }}</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">
                                    {{ $hasFilters ? 'No purchase orders match the current filters.' : 'No purchase orders have been created yet.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if ($purchaseOrders->hasPages())
            <div>{{ $purchaseOrders->links() }}</div>
        @endif
    </div>
</div>
