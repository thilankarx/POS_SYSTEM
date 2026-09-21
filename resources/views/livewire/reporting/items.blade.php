<div>
    @section('title', 'Items report')
    @include('livewire.reporting._page-heading', ['description' => 'Compare product sales, costs, and margin with location and category filters.'])

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
            <label for="stock_location_id" class="mb-1 block text-sm font-medium">Location</label>
            <select wire:model.live="stock_location_id" id="stock_location_id" class="rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                <option value="">All locations</option>
                @foreach ($stockLocations as $location)
                    <option value="{{ $location->id }}">{{ $location->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="category_id" class="mb-1 block text-sm font-medium">Category</label>
            <select wire:model.live="category_id" id="category_id" class="rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="businessType" class="mb-1 block text-sm font-medium">Business type</label>
            <select wire:model.live="businessType" id="businessType" class="rounded-md border border-gray-300 px-3 py-2 text-sm capitalize dark:border-gray-600 dark:bg-gray-800">
                @foreach ($businessTypes as $type)
                    <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                @endforeach
                <option value="all">All business types</option>
            </select>
        </div>
        <a href="{{ route('reports.items.export', ['from' => $from, 'to' => $to, 'stock_location_id' => $stock_location_id, 'category_id' => $category_id, 'business_type' => $businessType]) }}" class="inline-flex h-10 items-center rounded-md bg-emerald-700 px-4 text-sm font-semibold text-white hover:bg-emerald-800 dark:bg-emerald-600 dark:hover:bg-emerald-500">
            Export CSV
        </a>
        <a href="{{ route('reports.items.export-pdf', ['from' => $from, 'to' => $to, 'stock_location_id' => $stock_location_id, 'category_id' => $category_id, 'business_type' => $businessType]) }}" target="_blank" rel="noopener" class="inline-flex h-10 items-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:hover:bg-slate-800">
            Export PDF
        </a>
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <table class="w-full min-w-[900px] text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-2">Item</th>
                    <th class="px-4 py-2">SKU</th>
                    <th class="px-4 py-2">Category</th>
                    <th class="px-4 py-2 text-right">Qty sold</th>
                    <th class="px-4 py-2 text-right">Revenue</th>
                    <th class="px-4 py-2 text-right">Cost</th>
                    <th class="px-4 py-2 text-right">Margin</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr class="border-b border-slate-100 transition-colors hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/50">
                        <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ $row->item_name }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-500 dark:text-slate-400">{{ $row->sku ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $row->category_name ?: 'Uncategorized' }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-slate-700 dark:text-slate-200">{{ number_format((float) $row->quantity, 2) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums text-slate-900 dark:text-white">{{ $row->line_total }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-slate-600 dark:text-slate-300">{{ $row->cost_price }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums {{ $row->line_total->minus($row->cost_price)->isNegative() ? 'text-rose-700 dark:text-rose-400' : 'text-emerald-700 dark:text-emerald-400' }}">{{ $row->line_total->minus($row->cost_price) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No sales in range.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
