<div>
    @section('title', 'Supplier Invoices')

    @php
        $statusStyles = [
            'open' => 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200',
            'partially_paid' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
            'paid' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
            'disputed' => 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200',
        ];
        $hasFilters = $search !== '' || $status !== '' || $supplier !== '' || $timing !== '' || $sort !== 'invoice_date';
    @endphp

    <div class="space-y-4">
        <div class="flex flex-col gap-3 border-b border-gray-200 pb-4 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold">Supplier invoices</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $summary['total'] }} invoices</p>
            </div>
            @can('create', \App\Domain\Purchasing\Models\SupplierInvoice::class)
                <a href="{{ route('supplier-invoices.create') }}" class="self-start rounded-md bg-[#1b1b18] px-4 py-2 text-sm font-medium text-white hover:bg-black dark:bg-white dark:text-[#1b1b18]">New supplier invoice</a>
            @endcan
        </div>

        <section class="grid grid-cols-2 gap-px overflow-hidden rounded-lg border border-gray-200 bg-gray-200 shadow-sm dark:border-gray-700 dark:bg-gray-700 lg:grid-cols-4">
            <div class="bg-white px-4 py-3 dark:bg-gray-900">
                <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Outstanding</div>
                <div class="mt-1 text-xl font-semibold">{{ $summary['outstanding'] }}</div>
            </div>
            <div class="bg-white px-4 py-3 dark:bg-gray-900">
                <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Overdue</div>
                <div class="mt-1 text-xl font-semibold {{ $summary['overdue'] > 0 ? 'text-red-700 dark:text-red-300' : '' }}">{{ $summary['overdue'] }}</div>
            </div>
            <div class="bg-white px-4 py-3 dark:bg-gray-900">
                <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Due in 7 days</div>
                <div class="mt-1 text-xl font-semibold text-amber-700 dark:text-amber-300">{{ $summary['dueSoon'] }}</div>
            </div>
            <div class="bg-white px-4 py-3 dark:bg-gray-900">
                <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Disputed</div>
                <div class="mt-1 text-xl font-semibold {{ $summary['disputed'] > 0 ? 'text-red-700 dark:text-red-300' : '' }}">{{ $summary['disputed'] }}</div>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div class="sm:col-span-2">
                    <label for="invoice_search" class="sr-only">Search supplier invoices</label>
                    <input wire:model.live.debounce.300ms="search" id="invoice_search" type="search" placeholder="Search invoice, supplier, or PO" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                </div>
                <div>
                    <label for="invoice_status" class="sr-only">Status</label>
                    <select wire:model.live="status" id="invoice_status" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                        <option value="">All statuses</option>
                        <option value="open">Open</option>
                        <option value="partially_paid">Partially paid</option>
                        <option value="paid">Paid</option>
                        <option value="disputed">Disputed</option>
                    </select>
                </div>
                <div>
                    <label for="invoice_supplier" class="sr-only">Supplier</label>
                    <select wire:model.live="supplier" id="invoice_supplier" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                        <option value="">All suppliers</option>
                        @foreach ($suppliers as $supplierOption)
                            <option value="{{ $supplierOption->id }}">{{ $supplierOption->company_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="invoice_timing" class="sr-only">Due date</label>
                    <select wire:model.live="timing" id="invoice_timing" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                        <option value="">Any due date</option>
                        <option value="overdue">Overdue</option>
                        <option value="due_soon">Due in 7 days</option>
                        <option value="unscheduled">No due date</option>
                    </select>
                </div>
            </div>
            <div class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-3 dark:border-gray-800">
                <div class="text-sm text-gray-500 dark:text-gray-400">Showing {{ $supplierInvoices->firstItem() ?? 0 }}-{{ $supplierInvoices->lastItem() ?? 0 }} of {{ $supplierInvoices->total() }}</div>
                <div class="flex items-center gap-2">
                    <label for="invoice_sort" class="text-sm text-gray-500 dark:text-gray-400">Sort</label>
                    <select wire:model.live="sort" id="invoice_sort" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm dark:border-gray-600 dark:bg-gray-800">
                        <option value="invoice_date">Newest invoice</option>
                        <option value="due_date">Due date</option>
                        <option value="balance">Highest balance</option>
                    </select>
                    @if ($hasFilters)
                        <button type="button" wire:click="clearFilters" class="px-2 py-1.5 text-sm text-gray-600 hover:underline dark:text-gray-300">Clear filters</button>
                    @endif
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900" wire:loading.class="opacity-60" wire:target="search,status,supplier,timing,sort,clearFilters">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1280px] text-left text-sm">
                    <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500 dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3">Invoice</th>
                            <th class="px-4 py-3">Supplier / PO</th>
                            <th class="px-4 py-3">Due date</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3">Payment progress</th>
                            <th class="px-4 py-3 text-right">Balance</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($supplierInvoices as $supplierInvoice)
                            @php
                                $balance = $supplierInvoice->total->minus($supplierInvoice->paid_total);
                                $totalAmount = (float) (string) $supplierInvoice->total->getAmount();
                                $paymentPercentage = $totalAmount > 0
                                    ? min(100, (int) round(((float) (string) $supplierInvoice->paid_total->getAmount() / $totalAmount) * 100))
                                    : 0;
                                $isOverdue = $supplierInvoice->status !== \App\Domain\Purchasing\Models\SupplierInvoice::STATUS_PAID
                                    && $supplierInvoice->due_date?->isBefore(today());
                                $daysFromDue = $supplierInvoice->due_date ? (int) today()->diffInDays($supplierInvoice->due_date) : null;
                            @endphp
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                <td class="px-4 py-3">
                                    @can('update', $supplierInvoice)
                                        <a href="{{ route('supplier-invoices.edit', $supplierInvoice) }}" class="font-semibold hover:underline">{{ $supplierInvoice->invoice_number }}</a>
                                    @else
                                        <span class="font-semibold">{{ $supplierInvoice->invoice_number }}</span>
                                    @endcan
                                    <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Invoice date {{ $supplierInvoice->invoice_date->format('Y-m-d') }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ $supplierInvoice->supplier?->company_name ?? 'Not set' }}</div>
                                    <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                        @if ($supplierInvoice->purchaseOrder)
                                            <a href="{{ route('purchase-orders.edit', $supplierInvoice->purchaseOrder) }}" class="hover:underline">{{ $supplierInvoice->purchaseOrder->number }}</a>
                                        @else
                                            No purchase order
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium {{ $isOverdue ? 'text-red-700 dark:text-red-300' : '' }}">{{ $supplierInvoice->due_date?->format('Y-m-d') ?? 'Not scheduled' }}</div>
                                    @if ($isOverdue)
                                        <div class="mt-0.5 text-xs font-medium text-red-600 dark:text-red-400">{{ $daysFromDue }} {{ \Illuminate\Support\Str::plural('day', $daysFromDue) }} overdue</div>
                                    @elseif ($supplierInvoice->due_date && $supplierInvoice->status !== \App\Domain\Purchasing\Models\SupplierInvoice::STATUS_PAID)
                                        <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $daysFromDue === 0 ? 'Due today' : 'Due in '.$daysFromDue.' '.\Illuminate\Support\Str::plural('day', $daysFromDue) }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-medium tabular-nums">{{ $supplierInvoice->total }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <div class="h-1.5 w-24 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700"><div class="h-full bg-emerald-600" style="width: {{ $paymentPercentage }}%"></div></div>
                                        <span class="text-xs font-medium">{{ $paymentPercentage }}%</span>
                                    </div>
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Paid {{ $supplierInvoice->paid_total }}</div>
                                </td>
                                <td class="px-4 py-3 text-right font-semibold tabular-nums {{ $isOverdue ? 'text-red-700 dark:text-red-300' : '' }}">{{ $balance }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $statusStyles[$supplierInvoice->status] ?? $statusStyles['open'] }}">{{ str_replace('_', ' ', $supplierInvoice->status) }}</span>
                                    @if ($supplierInvoice->status === \App\Domain\Purchasing\Models\SupplierInvoice::STATUS_DISPUTED)
                                        <div class="mt-1 max-w-48 text-xs text-red-600 dark:text-red-400">Invoice {{ $supplierInvoice->total }} vs received {{ $receivedTotals[$supplierInvoice->id] }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        @can('update', $supplierInvoice)
                                            <a href="{{ route('supplier-invoices.edit', $supplierInvoice) }}" class="font-medium text-gray-700 hover:underline dark:text-gray-300">Edit</a>
                                        @endcan
                                        @can('view', $supplierInvoice)
                                            <a href="{{ route('supplier-invoices.pdf', $supplierInvoice) }}" target="_blank" class="font-medium text-gray-700 hover:underline dark:text-gray-300">PDF</a>
                                        @endcan
                                    </div>

                                    @can('update', $supplierInvoice)
                                        @if ($supplierInvoice->status === \App\Domain\Purchasing\Models\SupplierInvoice::STATUS_DISPUTED)
                                            @can('approve', $supplierInvoice)
                                                <button wire:click="resolveDispute({{ $supplierInvoice->id }})" wire:confirm="Accept this discrepancy and allow payment?" wire:loading.attr="disabled" wire:target="resolveDispute({{ $supplierInvoice->id }})" class="mt-2 rounded-md border border-amber-300 px-2.5 py-1.5 text-xs font-medium text-amber-700 hover:bg-amber-50 disabled:opacity-60 dark:border-amber-700 dark:text-amber-300 dark:hover:bg-amber-950">Accept discrepancy</button>
                                            @endcan
                                            @error("dispute.{$supplierInvoice->id}") <p class="mt-1 max-w-56 text-xs text-red-600">{{ $message }}</p> @enderror
                                        @elseif ($supplierInvoice->status !== \App\Domain\Purchasing\Models\SupplierInvoice::STATUS_PAID)
                                            <div class="mt-2 flex items-center gap-2">
                                                <label for="payment_{{ $supplierInvoice->id }}" class="sr-only">Payment amount for {{ $supplierInvoice->invoice_number }}</label>
                                                <input wire:model="paymentAmount.{{ $supplierInvoice->id }}" id="payment_{{ $supplierInvoice->id }}" type="text" inputmode="decimal" class="w-28 rounded-md border border-gray-300 px-2 py-1.5 text-right text-sm tabular-nums dark:border-gray-600 dark:bg-gray-800">
                                                <button wire:click="recordPayment({{ $supplierInvoice->id }})" wire:loading.attr="disabled" wire:target="recordPayment({{ $supplierInvoice->id }})" class="rounded-md bg-emerald-600 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-emerald-700 disabled:opacity-60">Record payment</button>
                                            </div>
                                            @error("paymentAmount.{$supplierInvoice->id}") <p class="mt-1 max-w-64 text-xs text-red-600">{{ $message }}</p> @enderror
                                            @error("payment.{$supplierInvoice->id}") <p class="mt-1 max-w-64 text-xs text-red-600">{{ $message }}</p> @enderror
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-10 text-center text-gray-500 dark:text-gray-400">{{ $hasFilters ? 'No supplier invoices match the current filters.' : 'No supplier invoices have been created yet.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if ($supplierInvoices->hasPages())
            <div>{{ $supplierInvoices->links() }}</div>
        @endif
    </div>
</div>
