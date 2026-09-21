<div>
    @section('title', 'Inventory report')
    @include('livewire.reporting._page-heading', ['description' => 'Trace stock movement across items, locations, dates, and movement reasons.'])

    <div class="mb-5 flex flex-wrap items-end gap-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
        <div>
            <label for="from" class="mb-1 block text-sm font-medium">From</label>
            <input wire:model.live.debounce.300ms="from" id="from" type="date" max="{{ $to }}" class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm focus:border-emerald-600 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
        </div>
        <div>
            <label for="to" class="mb-1 block text-sm font-medium">To</label>
            <input wire:model.live.debounce.300ms="to" id="to" type="date" min="{{ $from }}" class="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm focus:border-emerald-600 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
        </div>
        <div>
            <label for="item_id" class="mb-1 block text-sm font-medium">Item</label>
            <select wire:model.live="item_id" id="item_id" class="rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                <option value="">All items</option>
                @foreach ($items as $item)
                    <option value="{{ $item->id }}">{{ $item->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="stock_location_id" class="mb-1 block text-sm font-medium">Location</label>
            <select wire:model.live="stock_location_id" id="stock_location_id" class="rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                <option value="">All locations</option>
                @foreach ($stockLocations as $location)
                    <option value="{{ $location->id }}">{{ $location->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="reason" class="mb-1 block text-sm font-medium">Reason</label>
            <select wire:model.live="reason" id="reason" class="rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                <option value="">All reasons</option>
                <option value="sale">Sale</option>
                <option value="receiving">Receiving</option>
                <option value="transfer">Transfer</option>
                <option value="adjustment">Adjustment</option>
                <option value="count">Count</option>
                <option value="return">Return</option>
                <option value="return_to_supplier">Return to supplier</option>
            </select>
        </div>
        <a href="{{ route('reports.inventory.export', ['from' => $from, 'to' => $to, 'item_id' => $item_id, 'stock_location_id' => $stock_location_id, 'reason' => $reason]) }}" class="inline-flex h-10 items-center rounded-md bg-emerald-700 px-4 text-sm font-semibold text-white hover:bg-emerald-800 dark:bg-emerald-600 dark:hover:bg-emerald-500">
            Export CSV
        </a>
        <a href="{{ route('reports.inventory.export-pdf', ['from' => $from, 'to' => $to, 'item_id' => $item_id, 'stock_location_id' => $stock_location_id, 'reason' => $reason]) }}" target="_blank" rel="noopener" class="inline-flex h-10 items-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:hover:bg-slate-800">
            Export PDF
        </a>
    </div>

    <div class="mb-6 overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="border-b border-gray-200 px-4 py-2 text-sm font-medium dark:border-gray-700">Summary by reason</div>
        <table class="w-full min-w-[600px] text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-2">Reason</th>
                    <th class="px-4 py-2 text-right">Movements</th>
                    <th class="px-4 py-2 text-right">Qty in</th>
                    <th class="px-4 py-2 text-right">Qty out</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($summaryByReason as $row)
                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <td class="px-4 py-3 font-medium capitalize text-slate-800 dark:text-slate-200">{{ str_replace('_', ' ', $row->reason) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-slate-600 dark:text-slate-300">{{ number_format((int) $row->movement_count) }}</td>
                        <td class="px-4 py-3 text-right font-medium tabular-nums text-emerald-700 dark:text-emerald-400">{{ $row->quantity_in }}</td>
                        <td class="px-4 py-3 text-right font-medium tabular-nums text-rose-700 dark:text-rose-400">{{ $row->quantity_out }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No movements in range.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <table class="w-full min-w-[720px] text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-2">Date</th>
                    <th class="px-4 py-2">Item</th>
                    <th class="px-4 py-2">Location</th>
                    <th class="px-4 py-2 text-right">Delta</th>
                    <th class="px-4 py-2">Reason</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($movements as $movement)
                    <tr class="border-b border-slate-100 transition-colors hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/50">
                        <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">{{ $movement->occurred_at->format('d M Y, H:i') }}</td>
                        <td class="px-4 py-3 font-medium text-slate-900 dark:text-white">{{ $movement->item?->name }}</td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $movement->stockLocation?->name }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums {{ $movement->quantity_delta > 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400' }}">{{ $movement->quantity_delta > 0 ? '+' : '' }}{{ $movement->quantity_delta }}</td>
                        <td class="px-4 py-3 capitalize text-slate-600 dark:text-slate-300">{{ str_replace('_', ' ', $movement->reason) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No movements in range.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $movements->links() }}
    </div>
</div>
