<div>
    @section('title', $purchaseOrder ? "Purchase order {$purchaseOrder->number}" : 'New purchase order')

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
    @endphp

    <div class="max-w-6xl space-y-4">
        @if ($purchaseOrder)
            <div class="flex flex-col gap-4 border-b border-gray-200 pb-4 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-xl font-semibold">{{ $purchaseOrder->number }}</h2>
                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $statusStyles[$purchaseOrder->status] ?? $statusStyles['draft'] }}">
                            {{ str_replace('_', ' ', $purchaseOrder->status) }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $purchaseOrder->supplier?->company_name ?? 'No supplier' }}
                        <span aria-hidden="true">&middot;</span>
                        created {{ $purchaseOrder->created_at->format('Y-m-d H:i') }}
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('purchase-orders.index') }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium hover:border-gray-400 dark:border-gray-600 dark:hover:border-gray-500">Back</a>

                    @if ($purchaseOrder->status === \App\Domain\Purchasing\Models\PurchaseOrder::STATUS_DRAFT)
                        <button wire:click="submit" wire:loading.attr="disabled" wire:target="submit" class="rounded-md bg-[#1b1b18] px-3 py-2 text-sm font-medium text-white hover:bg-black disabled:cursor-not-allowed disabled:opacity-60 dark:bg-white dark:text-[#1b1b18]">
                            <span wire:loading.remove wire:target="submit">Submit for approval</span>
                            <span wire:loading wire:target="submit">Submitting&hellip;</span>
                        </button>
                        <button wire:click="cancel" wire:confirm="Cancel this purchase order?" wire:loading.attr="disabled" wire:target="cancel" class="px-2 py-2 text-sm font-medium text-red-600 hover:underline disabled:cursor-not-allowed disabled:opacity-60">
                            <span wire:loading.remove wire:target="cancel">Cancel</span>
                            <span wire:loading wire:target="cancel">Cancelling&hellip;</span>
                        </button>
                    @endif

                    @if ($purchaseOrder->status === \App\Domain\Purchasing\Models\PurchaseOrder::STATUS_SUBMITTED)
                        @can('approve', $purchaseOrder)
                            <button wire:click="approve" wire:loading.attr="disabled" wire:target="approve" class="rounded-md bg-[#1b1b18] px-3 py-2 text-sm font-medium text-white hover:bg-black disabled:cursor-not-allowed disabled:opacity-60 dark:bg-white dark:text-[#1b1b18]">
                                <span wire:loading.remove wire:target="approve">Approve</span>
                                <span wire:loading wire:target="approve">Approving&hellip;</span>
                            </button>
                        @endcan
                        <button wire:click="cancel" wire:confirm="Cancel this purchase order?" wire:loading.attr="disabled" wire:target="cancel" class="px-2 py-2 text-sm font-medium text-red-600 hover:underline disabled:cursor-not-allowed disabled:opacity-60">
                            <span wire:loading.remove wire:target="cancel">Cancel</span>
                            <span wire:loading wire:target="cancel">Cancelling&hellip;</span>
                        </button>
                    @endif

                    @if (in_array($purchaseOrder->status, ['approved', 'partially_received']) && \Illuminate\Support\Facades\Route::has('receivings.create'))
                        @can('create', \App\Domain\Purchasing\Models\Receiving::class)
                            <a href="{{ route('receivings.create', ['purchase_order' => $purchaseOrder->id]) }}" class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700">Receive goods</a>
                        @endcan
                    @endif
                </div>
            </div>
        @else
            <div class="flex flex-col gap-3 border-b border-gray-200 pb-4 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-xl font-semibold">New purchase order</h2>
                        <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200">Draft</span>
                    </div>
                </div>
                <a href="{{ route('purchase-orders.index') }}" class="self-start rounded-md border border-gray-300 px-3 py-2 text-sm font-medium hover:border-gray-400 dark:border-gray-600 dark:hover:border-gray-500">Back to orders</a>
            </div>
        @endif

        @error('lines') <p class="rounded-md bg-red-50 p-3 text-sm text-red-600 dark:bg-red-950">{{ $message }}</p> @enderror

        @if ($this->isEditable())
            <form wire:submit="save" class="space-y-4">
                <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <h3 class="mb-4 text-sm font-semibold">Order information</h3>
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <div>
                            <label for="supplier_id" class="mb-1 block text-sm font-medium">Supplier <span class="text-red-600">*</span></label>
                            <select wire:model="supplier_id" id="supplier_id" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                                <option value="">Select supplier&hellip;</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->company_name }}</option>
                                @endforeach
                            </select>
                            @error('supplier_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="stock_location_id" class="mb-1 block text-sm font-medium">Deliver to <span class="text-red-600">*</span></label>
                            <select wire:model="stock_location_id" id="stock_location_id" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                                <option value="">Select location&hellip;</option>
                                @foreach ($stockLocations as $location)
                                    <option value="{{ $location->id }}">{{ $location->name }}</option>
                                @endforeach
                            </select>
                            @error('stock_location_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="expected_on" class="mb-1 block text-sm font-medium">Expected delivery</label>
                            <input wire:model="expected_on" id="expected_on" type="date" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                            @error('expected_on') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="mt-4">
                        <label for="note" class="mb-1 block text-sm font-medium">Supplier instructions or internal note</label>
                        <textarea wire:model="note" id="note" rows="3" placeholder="Optional" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"></textarea>
                        @error('note') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </section>

                <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                        <div>
                            <h3 class="text-sm font-semibold">Order items</h3>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ count($lines) }} {{ \Illuminate\Support\Str::plural('line', count($lines)) }}</p>
                        </div>
                        <button type="button" wire:click="addLine" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium hover:border-gray-400 dark:border-gray-600 dark:hover:border-gray-500">+ Add item</button>
                    </div>

                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($lines as $index => $line)
                            @php $selectedItem = $items->firstWhere('id', (int) $line['item_id']); @endphp
                            <div class="px-4 py-4" wire:key="purchase-order-line-{{ $index }}">
                                <div class="mb-3 flex items-center justify-between">
                                    <div class="text-sm font-semibold">Line {{ $index + 1 }}</div>
                                    <button type="button" wire:click="removeLine({{ $index }})" aria-label="Remove line {{ $index + 1 }}" title="Remove line" class="h-8 w-8 rounded-md text-lg text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950">&times;</button>
                                </div>
                                <div class="grid grid-cols-1 gap-3 md:grid-cols-12">
                                    <div class="md:col-span-6">
                                        <label for="item_{{ $index }}" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Item <span class="text-red-600">*</span></label>
                                        <select wire:model.live="lines.{{ $index }}.item_id" id="item_{{ $index }}" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                                            <option value="">Select item&hellip;</option>
                                            @foreach ($items as $item)
                                                <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->sku }})</option>
                                            @endforeach
                                        </select>
                                        @if ($selectedItem)<p class="mt-1 text-xs text-gray-500 dark:text-gray-400">SKU {{ $selectedItem->sku }}</p>@endif
                                        @error("lines.{$index}.item_id") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="md:col-span-2">
                                        <label for="quantity_{{ $index }}" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Quantity <span class="text-red-600">*</span></label>
                                        <input wire:model.live.debounce.300ms="lines.{{ $index }}.quantity_ordered" id="quantity_{{ $index }}" type="text" inputmode="decimal" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm tabular-nums dark:border-gray-600 dark:bg-gray-800">
                                        @error("lines.{$index}.quantity_ordered") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="md:col-span-2">
                                        <label for="unit_cost_{{ $index }}" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Unit cost <span class="text-red-600">*</span></label>
                                        <input wire:model.live.debounce.300ms="lines.{{ $index }}.unit_cost" id="unit_cost_{{ $index }}" type="text" inputmode="decimal" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm tabular-nums dark:border-gray-600 dark:bg-gray-800">
                                        @error("lines.{$index}.unit_cost") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                    <div class="md:col-span-2">
                                        <div class="mb-1 text-xs font-medium text-gray-600 dark:text-gray-300">Line total</div>
                                        <div class="rounded-md bg-gray-50 px-3 py-2 text-right text-sm font-semibold tabular-nums dark:bg-gray-800">{{ $lineTotals[$index] }}</div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if ($lines === [])
                        <div class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No items have been added to this order.</div>
                    @endif
                </section>

                <section class="flex flex-col gap-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:flex-row sm:items-end sm:justify-between">
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="submit" @disabled($lines === []) wire:loading.attr="disabled" wire:target="save" class="rounded-md bg-[#1b1b18] px-4 py-2 text-sm font-medium text-white hover:bg-black disabled:cursor-not-allowed disabled:opacity-60 dark:bg-white dark:text-[#1b1b18]">
                            <span wire:loading.remove wire:target="save">Save draft</span>
                            <span wire:loading wire:target="save">Saving&hellip;</span>
                        </button>
                        <a href="{{ route('purchase-orders.index') }}" class="text-sm text-gray-500 hover:underline dark:text-gray-400">Cancel</a>
                    </div>
                    <table class="w-full text-sm sm:w-72">
                        <tr><td class="py-1 text-gray-500 dark:text-gray-400">Subtotal</td><td class="py-1 text-right tabular-nums">{{ $preview->subtotal }}</td></tr>
                        <tr><td class="py-1 text-gray-500 dark:text-gray-400">Tax</td><td class="py-1 text-right tabular-nums">{{ $preview->taxTotal }}</td></tr>
                        <tr class="font-semibold"><td class="border-t border-gray-200 pt-2 dark:border-gray-700">Order total</td><td class="border-t border-gray-200 pt-2 text-right tabular-nums dark:border-gray-700">{{ $preview->total }}</td></tr>
                    </table>
                </section>
            </form>
        @else
            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700"><h3 class="text-sm font-semibold">Order details</h3></div>
                <dl class="grid grid-cols-1 gap-px bg-gray-200 dark:bg-gray-700 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="bg-white px-4 py-3 dark:bg-gray-900"><dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Supplier</dt><dd class="mt-1 text-sm font-medium">{{ $purchaseOrder->supplier?->company_name ?? 'Not set' }}</dd></div>
                    <div class="bg-white px-4 py-3 dark:bg-gray-900"><dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Deliver to</dt><dd class="mt-1 text-sm font-medium">{{ $purchaseOrder->stockLocation?->name ?? 'Not set' }}</dd></div>
                    <div class="bg-white px-4 py-3 dark:bg-gray-900"><dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Expected on</dt><dd class="mt-1 text-sm font-medium">{{ $purchaseOrder->expected_on?->format('Y-m-d') ?? 'Not set' }}</dd></div>
                    <div class="bg-white px-4 py-3 dark:bg-gray-900"><dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Receipts</dt><dd class="mt-1 text-sm font-medium">{{ $purchaseOrder->receivings->count() }}</dd></div>
                    <div class="bg-white px-4 py-3 dark:bg-gray-900"><dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Created by</dt><dd class="mt-1 text-sm font-medium">{{ $purchaseOrder->createdBy?->name ?? 'Unknown' }}</dd></div>
                    <div class="bg-white px-4 py-3 dark:bg-gray-900"><dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Created at</dt><dd class="mt-1 text-sm font-medium">{{ $purchaseOrder->created_at->format('Y-m-d H:i') }}</dd></div>
                    <div class="bg-white px-4 py-3 dark:bg-gray-900"><dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Approved by</dt><dd class="mt-1 text-sm font-medium">{{ $purchaseOrder->approvedBy?->name ?? 'Not approved' }}</dd></div>
                    <div class="bg-white px-4 py-3 dark:bg-gray-900"><dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Approved at</dt><dd class="mt-1 text-sm font-medium">{{ $purchaseOrder->approved_at?->format('Y-m-d H:i') ?? 'Not approved' }}</dd></div>
                </dl>
                @if ($purchaseOrder->note)
                    <div class="border-t border-gray-200 px-4 py-3 dark:border-gray-700"><div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Note</div><p class="mt-1 whitespace-pre-line text-sm">{{ $purchaseOrder->note }}</p></div>
                @endif
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div><div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Ordered</div><div class="mt-1 text-lg font-semibold">{{ $formatQuantity($fulfilment['ordered']) }}</div></div>
                    <div><div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Received</div><div class="mt-1 text-lg font-semibold text-emerald-700 dark:text-emerald-400">{{ $formatQuantity($fulfilment['received']) }}</div></div>
                    <div><div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Remaining</div><div class="mt-1 text-lg font-semibold">{{ $formatQuantity($fulfilment['remaining']) }}</div></div>
                </div>
                <div class="mt-4 flex items-center gap-3">
                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700" role="progressbar" aria-label="Order fulfilment" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $fulfilment['percentage'] }}"><div class="h-full bg-emerald-600" style="width: {{ $fulfilment['percentage'] }}%"></div></div>
                    <span class="w-12 text-right text-sm font-medium">{{ $fulfilment['percentage'] }}%</span>
                </div>
            </section>

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700"><h3 class="text-sm font-semibold">Order lines</h3></div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[860px] text-left text-sm">
                        <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500 dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-400">
                            <tr><th class="px-4 py-3">#</th><th class="px-4 py-3">Item</th><th class="px-4 py-3 text-right">Ordered</th><th class="px-4 py-3 text-right">Received</th><th class="px-4 py-3 text-right">Remaining</th><th class="px-4 py-3">Fulfilment</th><th class="px-4 py-3 text-right">Unit cost</th><th class="px-4 py-3 text-right">Line total</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($purchaseOrder->lines as $line)
                                @php
                                    $remaining = $purchaseOrder->remainingQuantityFor($line);
                                    $isComplete = bccomp((string) $line->quantity_received, (string) $line->quantity_ordered, 3) >= 0;
                                    $isPartial = ! $isComplete && bccomp((string) $line->quantity_received, '0', 3) > 0;
                                @endphp
                                <tr>
                                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $line->line_number }}</td>
                                    <td class="px-4 py-3"><div class="font-medium">{{ $line->item?->name ?? $line->description }}</div><div class="text-xs text-gray-500 dark:text-gray-400">{{ $line->item?->sku ?? 'No SKU' }}</div></td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ $formatQuantity($line->quantity_ordered) }}</td>
                                    <td class="px-4 py-3 text-right font-medium tabular-nums">{{ $formatQuantity($line->quantity_received) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ $formatQuantity($remaining) }}</td>
                                    <td class="px-4 py-3">
                                        @if ($isComplete)
                                            <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">Complete</span>
                                        @elseif ($isPartial)
                                            <span class="inline-flex rounded-full bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-800 dark:bg-violet-950 dark:text-violet-200">Partial</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200">Awaiting</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ $line->unit_cost }}</td>
                                    <td class="px-4 py-3 text-right font-medium tabular-nums">{{ $line->line_total }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t border-gray-200 dark:border-gray-700">
                            <tr><td colspan="6"></td><th class="px-4 py-2 text-right font-normal text-gray-500 dark:text-gray-400">Subtotal</th><td class="px-4 py-2 text-right">{{ $purchaseOrder->subtotal }}</td></tr>
                            <tr><td colspan="6"></td><th class="px-4 py-2 text-right font-normal text-gray-500 dark:text-gray-400">Tax</th><td class="px-4 py-2 text-right">{{ $purchaseOrder->tax_total }}</td></tr>
                            <tr><td colspan="6"></td><th class="px-4 py-3 text-right font-semibold">Total</th><td class="px-4 py-3 text-right font-semibold">{{ $purchaseOrder->total }}</td></tr>
                        </tfoot>
                    </table>
                </div>
            </section>

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-700"><h3 class="text-sm font-semibold">Receiving history</h3><span class="text-xs text-gray-500 dark:text-gray-400">{{ $purchaseOrder->receivings->count() }} {{ \Illuminate\Support\Str::plural('receipt', $purchaseOrder->receivings->count()) }}</span></div>

                @forelse ($purchaseOrder->receivings as $receiving)
                    <article class="border-b border-gray-100 last:border-b-0 dark:border-gray-800">
                        <div class="flex flex-col gap-2 bg-gray-50 px-4 py-3 dark:bg-gray-800/40 sm:flex-row sm:items-center sm:justify-between">
                            <div><a href="{{ route('receivings.show', $receiving) }}" class="font-semibold text-gray-900 hover:underline dark:text-white">{{ $receiving->number }}</a><span class="ml-2 text-sm text-gray-500 dark:text-gray-400">{{ $receiving->received_at->format('Y-m-d H:i') }}</span></div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Received by {{ $receiving->user?->name ?? 'Unknown' }} @if ($receiving->supplier_reference)<span aria-hidden="true">&middot;</span> Ref {{ $receiving->supplier_reference }}@endif</div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full min-w-[900px] text-left text-sm">
                                <thead class="border-b border-gray-100 text-xs uppercase text-gray-500 dark:border-gray-800 dark:text-gray-400"><tr><th class="px-4 py-2">Item</th><th class="px-4 py-2">Lot</th><th class="px-4 py-2">Expires</th><th class="px-4 py-2 text-right">Quantity</th><th class="px-4 py-2 text-right">Unit cost</th><th class="px-4 py-2 text-right">Discount</th><th class="px-4 py-2 text-right">Line total</th></tr></thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach ($receiving->lines as $receivedLine)
                                        <tr>
                                            <td class="px-4 py-2"><div class="font-medium">{{ $receivedLine->item?->name ?? $receivedLine->description }}</div><div class="text-xs text-gray-500 dark:text-gray-400">{{ $receivedLine->item?->sku ?? 'No SKU' }}</div></td>
                                            <td class="px-4 py-2">{{ $receivedLine->stockLot?->lot_number ?? 'Not recorded' }}</td>
                                            <td class="px-4 py-2">{{ $receivedLine->stockLot?->expires_on?->format('Y-m-d') ?? 'Not set' }}</td>
                                            <td class="px-4 py-2 text-right tabular-nums">{{ $formatQuantity($receivedLine->quantity) }}</td>
                                            <td class="px-4 py-2 text-right tabular-nums">{{ $receivedLine->unit_cost }}</td>
                                            <td class="px-4 py-2 text-right tabular-nums">
                                                @if (bccomp((string) $receivedLine->discount_value, '0', 4) > 0)
                                                    {{ $receivedLine->discount_value }} {{ $receivedLine->discount_type === 'percent' ? '%' : $receivedLine->discount_type }}
                                                @else
                                                    None
                                                @endif
                                            </td>
                                            <td class="px-4 py-2 text-right font-medium tabular-nums">{{ $receivedLine->line_total }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="border-t border-gray-100 dark:border-gray-800">
                                    <tr><td colspan="6" class="px-4 py-1.5 text-right text-gray-500 dark:text-gray-400">Subtotal</td><td class="px-4 py-1.5 text-right">{{ $receiving->subtotal }}</td></tr>
                                    <tr><td colspan="6" class="px-4 py-1.5 text-right text-gray-500 dark:text-gray-400">Tax</td><td class="px-4 py-1.5 text-right">{{ $receiving->tax_total }}</td></tr>
                                    <tr><td colspan="6" class="px-4 py-2 text-right font-medium">Receipt total</td><td class="px-4 py-2 text-right font-semibold">{{ $receiving->total }}</td></tr>
                                </tfoot>
                            </table>
                        </div>
                        @if ($receiving->comment)<p class="border-t border-gray-100 px-4 py-2 text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">{{ $receiving->comment }}</p>@endif
                    </article>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No goods have been received against this order yet.</div>
                @endforelse
            </section>
        @endif
    </div>
</div>
