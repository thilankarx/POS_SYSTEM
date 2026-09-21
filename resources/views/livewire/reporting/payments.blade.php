<div>
    @section('title', 'Payments report')
    @include('livewire.reporting._page-heading', ['description' => 'Review payment totals by tender method and inspect individual transactions.'])

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
            <label for="payment_method_id" class="mb-1 block text-sm font-medium">Method</label>
            <select wire:model.live="payment_method_id" id="payment_method_id" class="rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                <option value="">All methods</option>
                @foreach ($paymentMethods as $method)
                    <option value="{{ $method->id }}">{{ $method->name }}</option>
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
        <a href="{{ route('reports.payments.export', ['from' => $from, 'to' => $to, 'payment_method_id' => $payment_method_id, 'stock_location_id' => $stock_location_id]) }}" class="inline-flex h-10 items-center rounded-md bg-emerald-700 px-4 text-sm font-semibold text-white hover:bg-emerald-800 dark:bg-emerald-600 dark:hover:bg-emerald-500">
            Export CSV
        </a>
        <a href="{{ route('reports.payments.export-pdf', ['from' => $from, 'to' => $to, 'payment_method_id' => $payment_method_id, 'stock_location_id' => $stock_location_id]) }}" target="_blank" rel="noopener" class="inline-flex h-10 items-center rounded-md border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:hover:bg-slate-800">
            Export PDF
        </a>
    </div>

    <div class="mb-6 overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="border-b border-gray-200 px-4 py-2 text-sm font-medium dark:border-gray-700">Summary by method</div>
        <table class="w-full min-w-[560px] text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-2">Method</th>
                    <th class="px-4 py-2 text-right">Payments</th>
                    <th class="px-4 py-2 text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($summaryByMethod as $row)
                    <tr class="border-b border-slate-100 transition-colors hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/50">
                        <td class="px-4 py-3 font-semibold text-slate-900 dark:text-white">{{ $row->method_name }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-slate-700 dark:text-slate-200">{{ number_format((int) $row->payment_count) }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums text-slate-900 dark:text-white">{{ $row->amount }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No payments in range.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <table class="w-full min-w-[760px] text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-2">Date</th>
                    <th class="px-4 py-2">Sale #</th>
                    <th class="px-4 py-2">Method</th>
                    <th class="px-4 py-2 text-right">Amount</th>
                    <th class="px-4 py-2">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payments as $payment)
                    <tr class="border-b border-slate-100 transition-colors hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/50">
                        <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">{{ $payment->created_at->format('d M Y, H:i') }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-200">{{ $payment->sale?->number ?? '—' }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-200">{{ $payment->method?->name ?? '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums text-slate-900 dark:text-white">{{ $payment->amount }}</td>
                        <td class="px-4 py-3"><span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold capitalize text-slate-700 dark:bg-slate-800 dark:text-slate-300">{{ $payment->status }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">No payments in range.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $payments->links() }}
    </div>
</div>
