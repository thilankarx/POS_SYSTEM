<div>
    @section('title', $supplierInvoice ? 'Edit supplier invoice' : 'New supplier invoice')

    @php
        $formatMoney = static fn ($amount) => $currency.' '.number_format((float) (string) $amount, 2);
        $statusStyles = [
            'open' => 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200',
            'partially_paid' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
            'paid' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
            'disputed' => 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200',
        ];
        $invoiceStatus = $supplierInvoice?->status ?? 'open';
    @endphp

    <div class="max-w-6xl space-y-4">
        <div class="flex flex-col gap-3 border-b border-gray-200 pb-4 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-xl font-semibold">{{ $supplierInvoice ? $supplierInvoice->invoice_number : 'New supplier invoice' }}</h2>
                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $statusStyles[$invoiceStatus] ?? $statusStyles['open'] }}">
                        {{ str_replace('_', ' ', $invoiceStatus) }}
                    </span>
                </div>
                @if ($supplierInvoice)
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $supplierInvoice->supplier?->company_name }}
                        <span aria-hidden="true">&middot;</span>
                        entered {{ $supplierInvoice->created_at->format('Y-m-d H:i') }}
                    </p>
                @endif
            </div>

            <a href="{{ route('supplier-invoices.index') }}" class="inline-flex self-start items-center gap-2 rounded-md border border-gray-300 px-3 py-2 text-sm font-medium hover:border-gray-400 dark:border-gray-600 dark:hover:border-gray-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                </svg>
                Back to invoices
            </a>
        </div>

        <form wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3 lg:items-start">
                <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:p-5 lg:col-span-2">
                    <div class="mb-5 flex items-center gap-3">
                        <span class="grid h-9 w-9 place-items-center rounded-md bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 14l2 2 4-4M7 3h10a2 2 0 012 2v14a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2zm2 5h6" />
                            </svg>
                        </span>
                        <div>
                            <h3 class="text-sm font-semibold">Invoice information</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Supplier document and payment terms</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label for="supplier_id" class="mb-1 block text-sm font-medium">Supplier <span class="text-red-600">*</span></label>
                            <select wire:model.live="supplier_id" id="supplier_id" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                                <option value="">Select supplier&hellip;</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->company_name }}</option>
                                @endforeach
                            </select>
                            @error('supplier_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="purchase_order_id" class="mb-1 block text-sm font-medium">Purchase order</label>
                            <select wire:model.live="purchase_order_id" id="purchase_order_id" @disabled(! $supplier_id) class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-400 dark:border-gray-600 dark:bg-gray-800 dark:disabled:bg-gray-800/50">
                                <option value="">No linked order</option>
                                @foreach ($purchaseOrders as $purchaseOrder)
                                    <option value="{{ $purchaseOrder->id }}">{{ $purchaseOrder->number }} &middot; {{ str_replace('_', ' ', $purchaseOrder->status) }} &middot; {{ $formatMoney($purchaseOrder->total->getAmount()) }}</option>
                                @endforeach
                            </select>
                            @error('purchase_order_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="invoice_number" class="mb-1 block text-sm font-medium">Invoice number <span class="text-red-600">*</span></label>
                            <input wire:model="invoice_number" id="invoice_number" type="text" maxlength="64" autocomplete="off" placeholder="Supplier invoice reference" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                            @error('invoice_number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="total" class="mb-1 block text-sm font-medium">Invoice total <span class="text-red-600">*</span></label>
                            <div class="flex rounded-md border border-gray-300 bg-white focus-within:border-gray-500 dark:border-gray-600 dark:bg-gray-800">
                                <span class="flex items-center border-r border-gray-300 px-3 text-xs font-semibold text-gray-500 dark:border-gray-600 dark:text-gray-400">{{ $currency }}</span>
                                <input wire:model.live.debounce.400ms="total" id="total" type="text" inputmode="decimal" class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2 text-right text-sm tabular-nums outline-none" aria-label="Invoice total in {{ $currency }}">
                            </div>
                            @error('total') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="invoice_date" class="mb-1 block text-sm font-medium">Invoice date <span class="text-red-600">*</span></label>
                            <input wire:model="invoice_date" id="invoice_date" type="date" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                            @error('invoice_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="due_date" class="mb-1 block text-sm font-medium">Due date</label>
                            <input wire:model="due_date" id="due_date" type="date" min="{{ $invoice_date }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                            @error('due_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </section>

                <aside class="space-y-4 lg:sticky lg:top-20">
                    <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                            <h3 class="text-sm font-semibold">Match summary</h3>
                        </div>

                        <dl class="divide-y divide-gray-100 text-sm dark:divide-gray-800">
                            <div class="flex items-start justify-between gap-4 px-4 py-3">
                                <dt class="text-gray-500 dark:text-gray-400">Supplier</dt>
                                <dd class="max-w-44 text-right font-medium">{{ $selectedSupplier?->company_name ?? 'Not selected' }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-4 px-4 py-3">
                                <dt class="text-gray-500 dark:text-gray-400">Purchase order</dt>
                                <dd class="text-right font-medium">{{ $selectedPurchaseOrder?->number ?? 'Not linked' }}</dd>
                            </div>
                            @if ($selectedPurchaseOrder)
                                <div class="flex items-start justify-between gap-4 px-4 py-3">
                                    <dt class="text-gray-500 dark:text-gray-400">Order total</dt>
                                    <dd class="text-right font-medium tabular-nums">{{ $formatMoney($selectedPurchaseOrder->total->getAmount()) }}</dd>
                                </div>
                                <div class="flex items-start justify-between gap-4 px-4 py-3">
                                    <dt class="text-gray-500 dark:text-gray-400">Received total</dt>
                                    <dd class="text-right font-medium tabular-nums">{{ $formatMoney($receivedTotal) }}</dd>
                                </div>
                                <div class="flex items-start justify-between gap-4 px-4 py-3">
                                    <dt class="text-gray-500 dark:text-gray-400">Invoice total</dt>
                                    <dd class="text-right font-medium tabular-nums">{{ $formatMoney(is_numeric($total) ? $total : 0) }}</dd>
                                </div>
                                <div class="flex items-start justify-between gap-4 px-4 py-3">
                                    <dt class="text-gray-500 dark:text-gray-400">Variance</dt>
                                    <dd class="text-right font-semibold tabular-nums {{ $matchesReceipts ? 'text-emerald-700 dark:text-emerald-400' : ($variance !== null ? 'text-red-700 dark:text-red-400' : '') }}">
                                        {{ $variance !== null ? $formatMoney($variance) : 'Pending' }}
                                    </dd>
                                </div>
                            @endif
                        </dl>

                        <div class="border-t border-gray-200 p-4 dark:border-gray-700">
                            @if (! $selectedPurchaseOrder)
                                <div class="flex items-center gap-2 rounded-md bg-gray-100 px-3 py-2.5 text-sm font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                    <span class="h-2 w-2 rounded-full bg-gray-400"></span>
                                    Unmatched invoice
                                </div>
                            @elseif ($variance === null)
                                <div class="flex items-center gap-2 rounded-md bg-amber-50 px-3 py-2.5 text-sm font-medium text-amber-800 dark:bg-amber-950 dark:text-amber-200">
                                    <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                                    Enter invoice total
                                </div>
                            @elseif ($matchesReceipts)
                                <div class="flex items-center gap-2 rounded-md bg-emerald-50 px-3 py-2.5 text-sm font-medium text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                    </svg>
                                    Receipt totals match
                                </div>
                            @else
                                <div class="flex items-center gap-2 rounded-md bg-red-50 px-3 py-2.5 text-sm font-medium text-red-800 dark:bg-red-950 dark:text-red-200">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 4h.01M10.3 3.8L2.7 17a2 2 0 001.7 3h15.2a2 2 0 001.7-3L13.7 3.8a2 2 0 00-3.4 0z" />
                                    </svg>
                                    Review variance
                                </div>
                            @endif
                        </div>
                    </section>

                    @if ($selectedSupplier)
                        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                            <h3 class="text-sm font-semibold">Supplier account</h3>
                            <dl class="mt-3 space-y-2 text-sm">
                                <div class="flex justify-between gap-3">
                                    <dt class="text-gray-500 dark:text-gray-400">Account</dt>
                                    <dd class="text-right font-medium">{{ $selectedSupplier->account_number ?: 'Not set' }}</dd>
                                </div>
                                <div class="flex justify-between gap-3">
                                    <dt class="text-gray-500 dark:text-gray-400">Contact</dt>
                                    <dd class="max-w-48 truncate text-right font-medium">{{ $selectedSupplier->person?->email ?: ($selectedSupplier->person?->phone ?: 'Not set') }}</dd>
                                </div>
                                @if ($selectedPurchaseOrder?->stockLocation)
                                    <div class="flex justify-between gap-3">
                                        <dt class="text-gray-500 dark:text-gray-400">Location</dt>
                                        <dd class="text-right font-medium">{{ $selectedPurchaseOrder->stockLocation->name }}</dd>
                                    </div>
                                @endif
                            </dl>
                        </section>
                    @endif
                </aside>
            </div>

            <section class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    @if ($supplierInvoice)
                        Last updated {{ $supplierInvoice->updated_at->format('Y-m-d H:i') }}
                    @else
                        Status on save: Open
                    @endif
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('supplier-invoices.index') }}" class="px-2 py-2 text-sm font-medium text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white">Cancel</a>
                    <button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex min-w-36 items-center justify-center rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60">
                        <span wire:loading.remove wire:target="save">{{ $supplierInvoice ? 'Save changes' : 'Record invoice' }}</span>
                        <span wire:loading wire:target="save">Saving&hellip;</span>
                    </button>
                </div>
            </section>
        </form>
    </div>
</div>
