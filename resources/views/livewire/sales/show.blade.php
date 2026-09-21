<div>
    @section('title', 'Sale '.$sale->number)

    @php
        $hasReturnableQuantity = $sale->sale_type !== \App\Domain\Sales\Models\Sale::TYPE_RETURN
            && in_array($sale->status, [\App\Domain\Sales\Models\Sale::STATUS_COMPLETED, \App\Domain\Sales\Models\Sale::STATUS_PARTIALLY_REFUNDED], true)
            && $sale->lines->contains(fn ($line) => bccomp($line->remainingReturnable(), '0', 3) > 0);
    @endphp

    <div class="mb-4 flex items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold">{{ $sale->sale_type === \App\Domain\Sales\Models\Sale::TYPE_INVOICE ? 'Invoice' : 'Sale' }} {{ $sale->invoice_number ?? $sale->number }}</h2>
            <div class="text-sm text-gray-500 dark:text-gray-400">
                {{ $sale->sold_at?->format('Y-m-d H:i') }}
                &middot; {{ str_replace('_', ' ', $sale->status) }}
                &middot; {{ $sale->customer?->company_name ?? 'Walk-in customer' }}
                @if ($sale->returnsSale)
                    &middot; returns <a href="{{ route('sales.show', $sale->returnsSale) }}" class="underline">{{ $sale->returnsSale->number }}</a>
                    @if ($sale->returnReason) ({{ $sale->returnReason->name }}) @endif
                @endif
                @if ($sale->status === \App\Domain\Sales\Models\Sale::STATUS_VOIDED)
                    &middot; voided by {{ $sale->voidedBy?->name }} on {{ $sale->voided_at?->format('Y-m-d H:i') }}
                    @if ($sale->void_reason) &mdash; {{ $sale->void_reason }} @endif
                @endif
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('sales.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium hover:border-gray-400 dark:border-gray-600">
                Back to sales
            </a>
            @can('refund', $sale)
                @if ($hasReturnableQuantity)
                    <a href="{{ route('sales.refund', $sale) }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium hover:border-gray-400 dark:border-gray-600">
                        Process return
                    </a>
                @endif
            @endcan
            @can('void', $sale)
                @if ($sale->status === \App\Domain\Sales\Models\Sale::STATUS_COMPLETED)
                    <a href="{{ route('sales.void', $sale) }}" class="rounded-md border border-red-300 px-4 py-2 text-sm font-medium text-red-600 hover:border-red-400 dark:border-red-800">
                        Void sale
                    </a>
                @endif
            @endcan
            <a href="{{ route('sales.receipt', $sale) }}" target="_blank" class="rounded-md bg-[#1b1b18] px-4 py-2 text-sm font-medium text-white hover:bg-black dark:bg-white dark:text-[#1b1b18]">
                Download {{ $sale->sale_type === \App\Domain\Sales\Models\Sale::TYPE_INVOICE ? 'invoice' : 'receipt' }} PDF
            </a>
        </div>
    </div>

    <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="border-b border-gray-200 px-4 py-2 text-sm font-medium dark:border-gray-700">Items</div>
        <table class="w-full text-left text-sm">
            <thead class="border-b border-gray-200 text-gray-500 dark:border-gray-700 dark:text-gray-400">
                <tr>
                    <th class="px-4 py-2">Item</th>
                    <th class="px-4 py-2">SKU</th>
                    <th class="px-4 py-2 text-right">Qty</th>
                    <th class="px-4 py-2 text-right">Unit price</th>
                    <th class="px-4 py-2 text-right">Discount</th>
                    <th class="px-4 py-2 text-right">Tax</th>
                    <th class="px-4 py-2 text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sale->lines as $line)
                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <td class="px-4 py-2">{{ $line->item_name }}</td>
                        <td class="px-4 py-2 text-gray-500 dark:text-gray-400">{{ $line->sku }}</td>
                        <td class="px-4 py-2 text-right">{{ rtrim(rtrim((string) $line->quantity, '0'), '.') }}</td>
                        <td class="px-4 py-2 text-right">{{ $line->unit_price }}</td>
                        <td class="px-4 py-2 text-right">{{ $line->discount_amount }}</td>
                        <td class="px-4 py-2 text-right">{{ $line->line_tax }}</td>
                        <td class="px-4 py-2 text-right">{{ $line->line_total }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">
        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-4 py-2 text-sm font-medium dark:border-gray-700">Payments</div>
            @if ($sale->payments->isEmpty())
                <div class="px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">No payment due ({{ $sale->sale_type }}).</div>
            @else
                <table class="w-full text-left text-sm">
                    <tbody>
                        @foreach ($sale->payments as $payment)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="px-4 py-2">{{ $payment->method?->name }}</td>
                                <td class="px-4 py-2 text-right">{{ $payment->amount }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-4 py-2 text-sm font-medium dark:border-gray-700">Totals</div>
            <table class="w-full text-left text-sm">
                <tbody>
                    {{-- $sale->subtotal is stored net of every line discount; add the
                    discount back for display so this line reconciles with the
                    Discount line below it (see receipt.blade.php for the same fix). --}}
                    <tr class="border-b border-gray-100 dark:border-gray-800"><td class="px-4 py-2">Subtotal</td><td class="px-4 py-2 text-right">{{ $sale->subtotal->plus($sale->discount_total) }}</td></tr>
                    <tr class="border-b border-gray-100 dark:border-gray-800"><td class="px-4 py-2">Discount</td><td class="px-4 py-2 text-right">-{{ $sale->discount_total }}</td></tr>
                    <tr class="border-b border-gray-100 dark:border-gray-800"><td class="px-4 py-2">Tax</td><td class="px-4 py-2 text-right">{{ $sale->tax_total }}</td></tr>
                    <tr class="border-b border-gray-100 font-semibold dark:border-gray-800"><td class="px-4 py-2">Total</td><td class="px-4 py-2 text-right">{{ $sale->total }}</td></tr>
                    <tr class="border-b border-gray-100 dark:border-gray-800"><td class="px-4 py-2">Paid</td><td class="px-4 py-2 text-right">{{ $sale->paid_total }}</td></tr>
                    <tr><td class="px-4 py-2">Change given</td><td class="px-4 py-2 text-right">{{ $sale->change_given }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    @if ($sale->returns->isNotEmpty())
        <div class="mt-6 overflow-x-auto rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-4 py-2 text-sm font-medium dark:border-gray-700">Returns</div>
            <table class="w-full text-left text-sm">
                <tbody>
                    @foreach ($sale->returns as $return)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="px-4 py-2"><a href="{{ route('sales.show', $return) }}" class="underline">{{ $return->number }}</a></td>
                            <td class="px-4 py-2 text-gray-500 dark:text-gray-400">{{ $return->returnReason?->name }}</td>
                            <td class="px-4 py-2 text-gray-500 dark:text-gray-400">{{ $return->sold_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-2 text-right">{{ $return->total }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
