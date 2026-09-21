<div>
    @section('title', 'Receiving '.$receiving->number)

    @php
        $formatQuantity = static fn ($quantity) => rtrim(rtrim((string) $quantity, '0'), '.');
        $typeMeta = match ($receiving->type) {
            \App\Domain\Purchasing\Models\Receiving::TYPE_RETURN_TO_SUPPLIER => [
                'label' => 'Return to supplier',
                'impact' => 'Stock out',
                'badge' => 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200',
                'impactBadge' => 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200',
            ],
            \App\Domain\Purchasing\Models\Receiving::TYPE_TRANSFER_IN => [
                'label' => 'Transfer in',
                'impact' => 'Stock in',
                'badge' => 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200',
                'impactBadge' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
            ],
            \App\Domain\Purchasing\Models\Receiving::TYPE_TRANSFER_OUT => [
                'label' => 'Transfer out',
                'impact' => 'Stock out',
                'badge' => 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
                'impactBadge' => 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200',
            ],
            default => [
                'label' => 'Supplier receipt',
                'impact' => 'Stock in',
                'badge' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
                'impactBadge' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
            ],
        };
        $totalQuantity = $receiving->lines->reduce(
            fn (string $total, $line) => bcadd($total, (string) $line->quantity, 3),
            '0.000',
        );
        $lotCount = $receiving->lines->pluck('stock_lot_id')->filter()->unique()->count();
    @endphp

    <div class="max-w-6xl space-y-4">
        <div class="flex flex-col gap-4 border-b border-gray-200 pb-4 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-xl font-semibold">{{ $receiving->number }}</h2>
                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium {{ $typeMeta['badge'] }}">{{ $typeMeta['label'] }}</span>
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Recorded {{ $receiving->received_at->format('Y-m-d H:i') }} by {{ $receiving->user?->name ?? 'Unknown user' }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('receivings.index') }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium hover:border-gray-400 dark:border-gray-600 dark:hover:border-gray-500">Back to receivings</a>
                @if ($receiving->purchaseOrder)
                    <a href="{{ route('purchase-orders.edit', $receiving->purchaseOrder) }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-medium hover:border-gray-400 dark:border-gray-600 dark:hover:border-gray-500">View purchase order</a>
                @endif
                @can('printLabels', $receiving)
                    <a href="{{ route('receivings.labels', $receiving) }}" class="rounded-md bg-[#1b1b18] px-3 py-2 text-sm font-medium text-white hover:bg-black dark:bg-white dark:text-[#1b1b18]">Print labels</a>
                @endcan
            </div>
        </div>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700"><h3 class="text-sm font-semibold">Receiving details</h3></div>
            <dl class="grid grid-cols-1 gap-px bg-gray-200 dark:bg-gray-700 sm:grid-cols-2 lg:grid-cols-4">
                <div class="bg-white px-4 py-3 dark:bg-gray-900">
                    <dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Supplier</dt>
                    <dd class="mt-1 text-sm font-medium">{{ $receiving->supplier?->company_name ?? 'Not applicable' }}</dd>
                </div>
                <div class="bg-white px-4 py-3 dark:bg-gray-900">
                    <dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Stock location</dt>
                    <dd class="mt-1 text-sm font-medium">{{ $receiving->stockLocation?->name ?? 'Not recorded' }}</dd>
                </div>
                <div class="bg-white px-4 py-3 dark:bg-gray-900">
                    <dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Transfer destination</dt>
                    <dd class="mt-1 text-sm font-medium">{{ $receiving->transferToLocation?->name ?? 'Not applicable' }}</dd>
                </div>
                <div class="bg-white px-4 py-3 dark:bg-gray-900">
                    <dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Inventory impact</dt>
                    <dd class="mt-1"><span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {{ $typeMeta['impactBadge'] }}">{{ $typeMeta['impact'] }}</span></dd>
                </div>
                <div class="bg-white px-4 py-3 dark:bg-gray-900">
                    <dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Purchase order</dt>
                    <dd class="mt-1 text-sm font-medium">
                        @if ($receiving->purchaseOrder)
                            <a href="{{ route('purchase-orders.edit', $receiving->purchaseOrder) }}" class="hover:underline">{{ $receiving->purchaseOrder->number }}</a>
                        @else
                            Ad hoc receiving
                        @endif
                    </dd>
                </div>
                <div class="bg-white px-4 py-3 dark:bg-gray-900">
                    <dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Supplier reference</dt>
                    <dd class="mt-1 text-sm font-medium">{{ $receiving->supplier_reference ?: 'Not provided' }}</dd>
                </div>
                <div class="bg-white px-4 py-3 dark:bg-gray-900">
                    <dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Received at</dt>
                    <dd class="mt-1 text-sm font-medium">{{ $receiving->received_at->format('Y-m-d H:i') }}</dd>
                </div>
                <div class="bg-white px-4 py-3 dark:bg-gray-900">
                    <dt class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Recorded by</dt>
                    <dd class="mt-1 text-sm font-medium">{{ $receiving->user?->name ?? 'Unknown user' }}</dd>
                </div>
            </dl>
            @if ($receiving->comment)
                <div class="border-t border-gray-200 px-4 py-3 dark:border-gray-700">
                    <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Comment</div>
                    <p class="mt-1 whitespace-pre-line text-sm">{{ $receiving->comment }}</p>
                </div>
            @endif
        </section>

        <section class="grid grid-cols-2 gap-px overflow-hidden rounded-lg border border-gray-200 bg-gray-200 shadow-sm dark:border-gray-700 dark:bg-gray-700 sm:grid-cols-4">
            <div class="bg-white px-4 py-3 dark:bg-gray-900">
                <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Item lines</div>
                <div class="mt-1 text-lg font-semibold">{{ $receiving->lines->count() }}</div>
            </div>
            <div class="bg-white px-4 py-3 dark:bg-gray-900">
                <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Total quantity</div>
                <div class="mt-1 text-lg font-semibold">{{ $formatQuantity($totalQuantity) }}</div>
            </div>
            <div class="bg-white px-4 py-3 dark:bg-gray-900">
                <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Lots</div>
                <div class="mt-1 text-lg font-semibold">{{ $lotCount }}</div>
            </div>
            <div class="bg-white px-4 py-3 dark:bg-gray-900">
                <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Receiving total</div>
                <div class="mt-1 text-lg font-semibold">{{ $receiving->total }}</div>
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700"><h3 class="text-sm font-semibold">Received items</h3></div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[1080px] text-left text-sm">
                    <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500 dark:border-gray-700 dark:bg-gray-800/50 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3">#</th>
                            <th class="px-4 py-3">Item</th>
                            <th class="px-4 py-3">Order line</th>
                            <th class="px-4 py-3">Lot and expiry</th>
                            <th class="px-4 py-3 text-right">Quantity</th>
                            <th class="px-4 py-3 text-right">Unit cost</th>
                            <th class="px-4 py-3 text-right">Selling price</th>
                            <th class="px-4 py-3 text-right">Discount</th>
                            <th class="px-4 py-3 text-right">Line total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($receiving->lines as $line)
                            <tr>
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $line->line_number }}</td>
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ $line->item?->name ?? $line->description ?? 'Unknown item' }}</div>
                                    <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $line->item?->sku ?? 'No SKU' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($line->purchaseOrderLine)
                                        <div class="font-medium">Line {{ $line->purchaseOrderLine->line_number }}</div>
                                        <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Ordered {{ $formatQuantity($line->purchaseOrderLine->quantity_ordered) }}</div>
                                    @else
                                        <span class="text-gray-500 dark:text-gray-400">Ad hoc</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ $line->stockLot?->lot_number ?? 'Not recorded' }}</div>
                                    <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Expires {{ $line->stockLot?->expires_on?->format('Y-m-d') ?? 'not set' }}</div>
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">
                                    <div class="font-medium">{{ $formatQuantity($line->quantity) }}</div>
                                    @if (bccomp((string) $line->pack_quantity, '1', 3) !== 0)
                                        <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Pack x {{ $formatQuantity($line->pack_quantity) }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $line->unit_cost }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ $line->stockLot?->selling_price ?? 'Not set' }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">
                                    @if (bccomp((string) $line->discount_value, '0', 4) > 0)
                                        {{ $line->discount_value }} {{ $line->discount_type === 'percent' ? '%' : $line->discount_type }}
                                    @else
                                        None
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-medium tabular-nums">{{ $line->line_total }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t border-gray-200 dark:border-gray-700">
                        <tr><td colspan="7"></td><th class="px-4 py-2 text-right font-normal text-gray-500 dark:text-gray-400">Subtotal</th><td class="px-4 py-2 text-right tabular-nums">{{ $receiving->subtotal }}</td></tr>
                        <tr><td colspan="7"></td><th class="px-4 py-2 text-right font-normal text-gray-500 dark:text-gray-400">Tax</th><td class="px-4 py-2 text-right tabular-nums">{{ $receiving->tax_total }}</td></tr>
                        <tr><td colspan="7"></td><th class="px-4 py-3 text-right font-semibold">Total</th><td class="px-4 py-3 text-right font-semibold tabular-nums">{{ $receiving->total }}</td></tr>
                    </tfoot>
                </table>
            </div>
        </section>
    </div>
</div>
