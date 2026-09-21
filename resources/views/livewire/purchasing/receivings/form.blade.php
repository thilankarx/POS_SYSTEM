<div>
    @section('title', $purchaseOrder ? "Receive {$purchaseOrder->number}" : 'New receiving')

    @php
        $formatQuantity = static fn ($quantity) => rtrim(rtrim((string) $quantity, '0'), '.');
        $orderedQuantity = '0.000';
        $receivedQuantity = '0.000';

        if ($purchaseOrder) {
            foreach ($purchaseOrder->lines as $purchaseOrderLine) {
                $orderedQuantity = bcadd($orderedQuantity, (string) $purchaseOrderLine->quantity_ordered, 3);
                $receivedQuantity = bcadd($receivedQuantity, (string) $purchaseOrderLine->quantity_received, 3);
            }
        }

        $remainingQuantity = bcsub($orderedQuantity, $receivedQuantity, 3);
        if (bccomp($remainingQuantity, '0', 3) < 0) {
            $remainingQuantity = '0.000';
        }
        $fulfilmentPercentage = bccomp($orderedQuantity, '0', 3) > 0
            ? min(100, (int) round(((float) $receivedQuantity / (float) $orderedQuantity) * 100))
            : 0;
    @endphp

    <div class="max-w-6xl space-y-4">
        <div class="flex flex-col gap-3 border-b border-gray-200 pb-4 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold">{{ $purchaseOrder ? 'Receive purchase order' : 'New receiving' }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    @if ($purchaseOrder)
                        {{ $purchaseOrder->number }} <span aria-hidden="true">&middot;</span> {{ $purchaseOrder->supplier?->company_name }}
                    @else
                        Record goods received into inventory
                    @endif
                </p>
            </div>
            <a href="{{ $purchaseOrder ? route('purchase-orders.edit', $purchaseOrder) : route('receivings.index') }}" class="self-start rounded-md border border-gray-300 px-3 py-2 text-sm font-medium hover:border-gray-400 dark:border-gray-600 dark:hover:border-gray-500">
                {{ $purchaseOrder ? 'Back to order' : 'Back to receivings' }}
            </a>
        </div>

        @if ($purchaseOrder)
            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="flex flex-col gap-3 border-b border-gray-200 px-4 py-3 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <a href="{{ route('purchase-orders.edit', $purchaseOrder) }}" class="font-semibold hover:underline">{{ $purchaseOrder->number }}</a>
                        <span class="ml-2 inline-flex rounded-full bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-800 dark:bg-violet-950 dark:text-violet-200">{{ str_replace('_', ' ', $purchaseOrder->status) }}</span>
                    </div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        Expected {{ $purchaseOrder->expected_on?->format('Y-m-d') ?? 'date not set' }}
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-px bg-gray-200 dark:bg-gray-700 sm:grid-cols-5">
                    <div class="bg-white px-4 py-3 dark:bg-gray-900 sm:col-span-2">
                        <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Deliver to</div>
                        <div class="mt-1 text-sm font-medium">{{ $purchaseOrder->stockLocation?->name }}</div>
                    </div>
                    <div class="bg-white px-4 py-3 dark:bg-gray-900">
                        <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Ordered</div>
                        <div class="mt-1 text-lg font-semibold">{{ $formatQuantity($orderedQuantity) }}</div>
                    </div>
                    <div class="bg-white px-4 py-3 dark:bg-gray-900">
                        <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Received</div>
                        <div class="mt-1 text-lg font-semibold text-emerald-700 dark:text-emerald-400">{{ $formatQuantity($receivedQuantity) }}</div>
                    </div>
                    <div class="bg-white px-4 py-3 dark:bg-gray-900">
                        <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Remaining</div>
                        <div class="mt-1 text-lg font-semibold">{{ $formatQuantity($remainingQuantity) }}</div>
                    </div>
                </div>
                <div class="flex items-center gap-3 px-4 py-3">
                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700" role="progressbar" aria-label="Purchase order fulfilment" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $fulfilmentPercentage }}">
                        <div class="h-full bg-emerald-600" style="width: {{ $fulfilmentPercentage }}%"></div>
                    </div>
                    <span class="w-12 text-right text-sm font-medium">{{ $fulfilmentPercentage }}%</span>
                </div>
            </section>
        @endif

        @error('lines') <p class="rounded-md bg-red-50 p-3 text-sm text-red-600 dark:bg-red-950 dark:text-red-300">{{ $message }}</p> @enderror

        <form wire:submit="save" class="space-y-4">
            <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h3 class="mb-4 text-sm font-semibold">Receiving details</h3>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    @if ($purchaseOrder)
                        <div>
                            <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Supplier</div>
                            <div class="mt-1 text-sm font-medium">{{ $purchaseOrder->supplier?->company_name }}</div>
                        </div>
                        <div>
                            <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Location</div>
                            <div class="mt-1 text-sm font-medium">{{ $purchaseOrder->stockLocation?->name }}</div>
                        </div>
                        <div>
                            <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Type</div>
                            <div class="mt-1 text-sm font-medium">Purchase order receipt</div>
                        </div>
                    @else
                        <div>
                            <label for="supplier_id" class="mb-1 block text-sm font-medium">Supplier</label>
                            <select wire:model="supplier_id" id="supplier_id" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                                <option value="">Select&hellip;</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->company_name }}</option>
                                @endforeach
                            </select>
                            @error('supplier_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="stock_location_id" class="mb-1 block text-sm font-medium">Location</label>
                            <select wire:model="stock_location_id" id="stock_location_id" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                                <option value="">Select&hellip;</option>
                                @foreach ($stockLocations as $location)
                                    <option value="{{ $location->id }}">{{ $location->name }}</option>
                                @endforeach
                            </select>
                            @error('stock_location_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="type" class="mb-1 block text-sm font-medium">Type</label>
                            <select wire:model="type" id="type" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                                <option value="receipt">Receipt</option>
                                <option value="return_to_supplier">Return to supplier</option>
                                <option value="transfer_in">Transfer in</option>
                                <option value="transfer_out">Transfer out</option>
                            </select>
                            @error('type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label for="supplier_reference" class="mb-1 block text-sm font-medium">Supplier reference</label>
                        <input wire:model="supplier_reference" id="supplier_reference" type="text" placeholder="Invoice or delivery note number" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                        @error('supplier_reference') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="comment" class="mb-1 block text-sm font-medium">Comment</label>
                        <input wire:model="comment" id="comment" type="text" placeholder="Optional receiving note" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                        @error('comment') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                    <div>
                        <h3 class="text-sm font-semibold">Items being received</h3>
                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ count($lines) }} {{ \Illuminate\Support\Str::plural('line', count($lines)) }}</p>
                    </div>
                    @if (! $purchaseOrder)
                        <button type="button" wire:click="addLine" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium hover:border-gray-400 dark:border-gray-600 dark:hover:border-gray-500">+ Add item</button>
                    @endif
                </div>

                <div class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($lines as $index => $line)
                        @php
                            $selectedItem = $items->firstWhere('id', (int) $line['item_id']);
                            $purchaseOrderLine = $purchaseOrder?->lines->firstWhere('id', (int) ($line['purchase_order_line_id'] ?? 0));
                            $lineRemaining = $purchaseOrderLine ? $purchaseOrder->remainingQuantityFor($purchaseOrderLine) : null;
                        @endphp

                        <div class="px-4 py-4" wire:key="receiving-line-{{ $index }}">
                            <div class="mb-4 flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    @if ($purchaseOrderLine)
                                        <div class="font-medium">{{ $selectedItem?->name }}</div>
                                        <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $selectedItem?->sku }}</div>
                                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                                            <span class="text-gray-500 dark:text-gray-400">Ordered <strong class="text-gray-900 dark:text-white">{{ $formatQuantity($purchaseOrderLine->quantity_ordered) }}</strong></span>
                                            <span class="text-gray-500 dark:text-gray-400">Previously received <strong class="text-gray-900 dark:text-white">{{ $formatQuantity($purchaseOrderLine->quantity_received) }}</strong></span>
                                            <span class="text-gray-500 dark:text-gray-400">Remaining <strong class="text-violet-700 dark:text-violet-300">{{ $formatQuantity($lineRemaining) }}</strong></span>
                                        </div>
                                    @else
                                        <label for="item_{{ $index }}" class="mb-1 block text-sm font-medium">Item</label>
                                        <select wire:model.live="lines.{{ $index }}.item_id" id="item_{{ $index }}" class="w-full max-w-xl rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                                            <option value="">Select item&hellip;</option>
                                            @foreach ($items as $item)
                                                <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->sku }})</option>
                                            @endforeach
                                        </select>
                                        @error("lines.{$index}.item_id") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    @endif
                                </div>
                                <button type="button" wire:click="removeLine({{ $index }})" aria-label="{{ $purchaseOrder ? 'Skip this order line' : 'Remove item' }}" title="{{ $purchaseOrder ? 'Skip this order line' : 'Remove item' }}" class="h-8 w-8 shrink-0 rounded-md text-lg text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950">&times;</button>
                            </div>

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
                                <div>
                                    <label for="quantity_{{ $index }}" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Receive quantity</label>
                                    <input wire:model.live.debounce.400ms="lines.{{ $index }}.quantity" id="quantity_{{ $index }}" type="text" inputmode="decimal" @if ($lineRemaining !== null) aria-describedby="quantity_limit_{{ $index }}" @endif class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm tabular-nums dark:border-gray-600 dark:bg-gray-800">
                                    @if ($lineRemaining !== null)<p id="quantity_limit_{{ $index }}" class="mt-1 text-xs text-gray-500 dark:text-gray-400">Maximum {{ $formatQuantity($lineRemaining) }}</p>@endif
                                    @error("lines.{$index}.quantity") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>

                                <div>
                                    <label for="unit_cost_{{ $index }}" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Unit cost</label>
                                    <input wire:model.live.debounce.400ms="lines.{{ $index }}.unit_cost" id="unit_cost_{{ $index }}" type="text" inputmode="decimal" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm tabular-nums dark:border-gray-600 dark:bg-gray-800">
                                    @error("lines.{$index}.unit_cost") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>

                                @if ($selectedItem?->movesStock())
                                    <div>
                                        <label for="selling_price_{{ $index }}" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Selling price <span class="text-red-600">*</span></label>
                                        <input wire:model.live.debounce.400ms="lines.{{ $index }}.selling_price" id="selling_price_{{ $index }}" type="text" inputmode="decimal" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm tabular-nums dark:border-gray-600 dark:bg-gray-800">
                                        @error("lines.{$index}.selling_price") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>

                                    <div>
                                        <label for="lot_number_{{ $index }}" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Lot number <span class="text-red-600">*</span></label>
                                        <input wire:model="lines.{{ $index }}.lot_number" id="lot_number_{{ $index }}" type="text" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                                        @error("lines.{$index}.lot_number") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>

                                    <div>
                                        <label for="expires_on_{{ $index }}" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">
                                            Expiry date
                                            @if ($selectedItem->has_expiry)
                                                <span class="text-red-600">*</span>
                                            @else
                                                <span class="font-normal text-gray-400">(optional)</span>
                                            @endif
                                        </label>
                                        <input wire:model="lines.{{ $index }}.expires_on" id="expires_on_{{ $index }}" type="date" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                                        @error("lines.{$index}.expires_on") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                @endif
                            </div>

                            @if ($selectedItem?->is_serialized && $type === \App\Domain\Purchasing\Models\Receiving::TYPE_RECEIPT)
                                <div class="mt-3">
                                    <label for="serials_{{ $index }}" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Serial numbers</label>
                                    <textarea wire:model="lines.{{ $index }}.serials_text" id="serials_{{ $index }}" rows="3" placeholder="One serial number per line" class="w-full rounded-md border border-gray-300 px-3 py-2 font-mono text-sm dark:border-gray-600 dark:bg-gray-800"></textarea>
                                    @error("lines.{$index}.serials_text") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if ($lines === [])
                    <div class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">{{ $purchaseOrder ? 'This purchase order has no outstanding quantities.' : 'No items selected for this receiving.' }}</div>
                @endif
            </section>

            <section class="flex flex-col gap-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:flex-row sm:items-end sm:justify-between">
                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" @disabled($lines === []) wire:loading.attr="disabled" wire:target="save" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60">
                        <span wire:loading.remove wire:target="save">Record receiving</span>
                        <span wire:loading wire:target="save">Recording&hellip;</span>
                    </button>
                    <a href="{{ $purchaseOrder ? route('purchase-orders.edit', $purchaseOrder) : route('receivings.index') }}" class="text-sm text-gray-500 hover:underline dark:text-gray-400">Cancel</a>
                </div>

                <table class="w-full text-sm sm:w-64">
                    <tr><td class="py-1 text-gray-500 dark:text-gray-400">Subtotal</td><td class="py-1 text-right tabular-nums">{{ $preview->subtotal }}</td></tr>
                    <tr><td class="py-1 text-gray-500 dark:text-gray-400">Tax</td><td class="py-1 text-right tabular-nums">{{ $preview->taxTotal }}</td></tr>
                    <tr class="font-semibold"><td class="border-t border-gray-200 pt-2 dark:border-gray-700">Receiving total</td><td class="border-t border-gray-200 pt-2 text-right tabular-nums dark:border-gray-700">{{ $preview->total }}</td></tr>
                </table>
            </section>
        </form>
    </div>
</div>
