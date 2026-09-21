<div>
    @section('title', 'Receivings report')
    @include('livewire.reporting._page-heading', ['description' => 'Review incoming stock, receiving values, suppliers, and stock destinations.'])

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
            <label for="supplier_id" class="mb-1 block text-sm font-medium">Supplier</label>
            <select wire:model.live="supplier_id" id="supplier_id" class="rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                <option value="">All suppliers</option>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}">{{ $supplier->company_name }}</option>
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
            <label for="type" class="mb-1 block text-sm font-medium">Type</label>
            <select wire:model.live="type" id="type" class="rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                <option value="">All types</option>
                @foreach ($types as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <a href="{{ route('reports.receivings.export', ['from' => $from, 'to' => $to, 'supplier_id' => $supplier_id, 'stock_location_id' => $stock_location_id, 'type' => $type]) }}" class="inline-flex h-10 items-center rounded-md bg-emerald-700 px-4 text-sm font-semibold text-white hover:bg-emerald-800 dark:bg-emerald-600 dark:hover:bg-emerald-500">
            Export CSV
        </a>
        <a href="{{ route('reports.receivings.export-pdf', ['from' => $from, 'to' => $to, 'supplier_id' => $supplier_id, 'stock_location_id' => $stock_location_id, 'type' => $type]) }}" target="_blank" rel="noopener" class="inline-flex h-10 items-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:hover:bg-slate-800">
            Export PDF
        </a>
    </div>

    <div class="mb-6 overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="border-b border-gray-200 px-4 py-2 text-sm font-medium dark:border-gray-700">Summary by type</div>
        <table class="w-full min-w-[620px] text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-2">Type</th>
                    <th class="px-4 py-2 text-right">Receivings</th>
                    <th class="px-4 py-2 text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($summaryByType as $row)
                    <tr class="border-b border-slate-100 transition-colors hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/50">
                        <td class="px-4 py-3 font-semibold capitalize text-slate-900 dark:text-white">{{ str_replace('_', ' ', $row->type) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-slate-700 dark:text-slate-200">{{ number_format((int) $row->receiving_count) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums text-slate-900 dark:text-white">{{ $row->total }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No receivings in range.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <table class="w-full min-w-[820px] text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-2">Number</th>
                    <th class="px-4 py-2">Supplier</th>
                    <th class="px-4 py-2">Location</th>
                    <th class="px-4 py-2">Type</th>
                    <th class="px-4 py-2 text-right">Total</th>
                    <th class="px-4 py-2">Received</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($receivings as $receiving)
                    <tr class="border-b border-slate-100 transition-colors hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/50">
                        <td class="whitespace-nowrap px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ $receiving->number }}</td>
                        <td class="px-4 py-3 text-slate-700 dark:text-slate-200">{{ $receiving->supplier?->company_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $receiving->stockLocation?->name ?? '—' }}</td>
                        <td class="px-4 py-3 capitalize text-slate-600 dark:text-slate-300">{{ str_replace('_', ' ', $receiving->type) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums text-slate-900 dark:text-white">{{ $receiving->total }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">{{ $receiving->received_at->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No receivings in range.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $receivings->links() }}
    </div>
</div>
