<div>
    @section('title', 'Reorder Suggestions')

    @php
        $formatQuantity = static fn ($quantity) => rtrim(rtrim((string) $quantity, '0'), '.');
        $hasFilters = $search !== '' || $urgency !== '';
    @endphp

    <div class="max-w-7xl space-y-4">
        <div class="flex flex-col gap-3 border-b border-gray-200 pb-4 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold">Reorder suggestions</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $summary['items'] }} items below their reorder point</p>
            </div>
            <a href="{{ route('purchase-orders.index') }}" class="self-start rounded-md border border-gray-300 px-3 py-2 text-sm font-medium hover:border-gray-400 dark:border-gray-600 dark:hover:border-gray-500">View purchase orders</a>
        </div>

        <section class="grid grid-cols-2 gap-px overflow-hidden rounded-lg border border-gray-200 bg-gray-200 shadow-sm dark:border-gray-700 dark:bg-gray-700 lg:grid-cols-4">
            <div class="bg-white px-4 py-3 dark:bg-gray-900">
                <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Suggested items</div>
                <div class="mt-1 text-xl font-semibold">{{ $summary['items'] }}</div>
            </div>
            <div class="bg-white px-4 py-3 dark:bg-gray-900">
                <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Out of stock</div>
                <div class="mt-1 text-xl font-semibold {{ $summary['outOfStock'] > 0 ? 'text-red-700 dark:text-red-300' : '' }}">{{ $summary['outOfStock'] }}</div>
            </div>
            <div class="bg-white px-4 py-3 dark:bg-gray-900">
                <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Suppliers</div>
                <div class="mt-1 text-xl font-semibold">{{ $summary['suppliers'] }}</div>
            </div>
            <div class="bg-white px-4 py-3 dark:bg-gray-900">
                <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Selected</div>
                <div class="mt-1 text-xl font-semibold text-emerald-700 dark:text-emerald-300">{{ $summary['selected'] }}</div>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-4">
                <div>
                    <label for="stock_location_id" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Stock location</label>
                    <select wire:model.live="stock_location_id" id="stock_location_id" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                        @foreach ($stockLocations as $location)
                            <option value="{{ $location->id }}">{{ $location->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label for="reorder_search" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Search</label>
                    <input wire:model.live.debounce.300ms="search" id="reorder_search" type="search" placeholder="Item, SKU, or supplier" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                </div>
                <div>
                    <label for="urgency" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">Stock status</label>
                    <select wire:model.live="urgency" id="urgency" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                        <option value="">All shortages</option>
                        <option value="out_of_stock">Out of stock</option>
                        <option value="low_stock">Low stock</option>
                    </select>
                </div>
            </div>
            @if ($hasFilters)
                <div class="mt-3 border-t border-gray-100 pt-3 text-right dark:border-gray-800">
                    <button type="button" wire:click="clearFilters" class="text-sm text-gray-600 hover:underline dark:text-gray-300">Clear filters</button>
                </div>
            @endif
        </section>

        @error('lines') <p class="rounded-md bg-red-50 p-3 text-sm text-red-600 dark:bg-red-950 dark:text-red-300">{{ $message }}</p> @enderror

        <div class="space-y-4" wire:loading.class="opacity-60" wire:target="stock_location_id,search,urgency,clearFilters">
            @forelse ($bySupplier as $supplierId => $items)
                <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex flex-col gap-3 border-b border-gray-200 px-4 py-3 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="font-semibold">{{ $items->first()->supplier->company_name }}</h3>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                {{ $supplierSelectedCounts[$supplierId] ?? 0 }} selected
                                <span aria-hidden="true">&middot;</span>
                                Estimated {{ $supplierTotals[$supplierId] }}
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" wire:click="selectSupplier({{ $supplierId }}, true)" class="px-2 py-1.5 text-sm text-gray-600 hover:underline dark:text-gray-300">Select all</button>
                            <button type="button" wire:click="selectSupplier({{ $supplierId }}, false)" class="px-2 py-1.5 text-sm text-gray-600 hover:underline dark:text-gray-300">Clear</button>
                            @can('create', \App\Domain\Purchasing\Models\PurchaseOrder::class)
                                <button wire:click="createPurchaseOrder({{ $supplierId }})" @disabled(($supplierSelectedCounts[$supplierId] ?? 0) === 0) wire:loading.attr="disabled" wire:target="createPurchaseOrder({{ $supplierId }})" class="rounded-md bg-[#1b1b18] px-3 py-2 text-sm font-medium text-white hover:bg-black disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white dark:text-[#1b1b18]">
                                    <span wire:loading.remove wire:target="createPurchaseOrder({{ $supplierId }})">Create draft PO</span>
                                    <span wire:loading wire:target="createPurchaseOrder({{ $supplierId }})">Creating&hellip;</span>
                                </button>
                            @endcan
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[1120px] text-left text-sm">
                            <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500 dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-400">
                                <tr>
                                    <th class="w-12 px-4 py-3"><span class="sr-only">Selected</span></th>
                                    <th class="px-4 py-3">Item</th>
                                    <th class="px-4 py-3">Stock status</th>
                                    <th class="px-4 py-3 text-right">On hand</th>
                                    <th class="px-4 py-3 text-right">Reorder point</th>
                                    <th class="px-4 py-3 text-right">Shortage</th>
                                    <th class="px-4 py-3 text-right">Order quantity</th>
                                    <th class="px-4 py-3 text-right">Projected stock</th>
                                    <th class="px-4 py-3 text-right">Unit cost</th>
                                    <th class="px-4 py-3 text-right">Line total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($items as $item)
                                    @php
                                        $isOutOfStock = bccomp((string) $item->on_hand, '0', 3) <= 0;
                                        $shortage = bcsub((string) $item->reorder_level, (string) $item->on_hand, 3);
                                        $quantity = (string) ($quantities[$item->id] ?? '0');
                                        $projected = preg_match('/^\d{1,15}(\.\d{1,4})?$/', $quantity) === 1
                                            ? bcadd((string) $item->on_hand, $quantity, 3)
                                            : (string) $item->on_hand;
                                    @endphp
                                    <tr class="{{ ! empty($selected[$item->id]) ? '' : 'opacity-60' }}">
                                        <td class="px-4 py-3 align-top">
                                            <input wire:model.live="selected.{{ $item->id }}" type="checkbox" aria-label="Select {{ $item->name }}" class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="font-medium">{{ $item->name }}</div>
                                            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $item->sku }}</div>
                                        </td>
                                        <td class="px-4 py-3">
                                            @if ($isOutOfStock)
                                                <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800 dark:bg-red-950 dark:text-red-200">Out of stock</span>
                                            @else
                                                <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-950 dark:text-amber-200">Low stock</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right font-medium tabular-nums">{{ $formatQuantity($item->on_hand) }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ $formatQuantity($item->reorder_level) }}</td>
                                        <td class="px-4 py-3 text-right font-medium text-red-700 tabular-nums dark:text-red-300">{{ $formatQuantity($shortage) }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <label for="quantity_{{ $item->id }}" class="sr-only">Order quantity for {{ $item->name }}</label>
                                            <input wire:model.live.debounce.300ms="quantities.{{ $item->id }}" id="quantity_{{ $item->id }}" type="text" inputmode="decimal" class="w-28 rounded-md border border-gray-300 px-2 py-1.5 text-right text-sm tabular-nums dark:border-gray-600 dark:bg-gray-800">
                                            @error("quantities.{$item->id}") <p class="mt-1 max-w-40 text-xs text-red-600">{{ $message }}</p> @enderror
                                        </td>
                                        <td class="px-4 py-3 text-right font-medium tabular-nums">{{ $formatQuantity($projected) }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <label for="unit_cost_{{ $item->id }}" class="sr-only">Unit cost for {{ $item->name }}</label>
                                            <input wire:model.live.debounce.300ms="unitCosts.{{ $item->id }}" id="unit_cost_{{ $item->id }}" type="text" inputmode="decimal" class="w-28 rounded-md border border-gray-300 px-2 py-1.5 text-right text-sm tabular-nums dark:border-gray-600 dark:bg-gray-800">
                                            @error("unitCosts.{$item->id}") <p class="mt-1 max-w-40 text-xs text-red-600">{{ $message }}</p> @enderror
                                        </td>
                                        <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ $lineTotals[$item->id] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @empty
                <section class="rounded-lg border border-gray-200 bg-white px-4 py-10 text-center shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="font-medium">{{ $hasFilters ? 'No suggestions match the current filters.' : 'Stock levels are above their reorder points.' }}</div>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $hasFilters ? 'Clear the filters to review all shortages at this location.' : 'There are no purchase orders to prepare from suggestions.' }}</p>
                </section>
            @endforelse
        </div>
    </div>
</div>
