<div>
    @section('title', 'Print labels — '.$receiving->number)

    @php
        $printableLines = $receiving->lines->filter(fn ($line) => $line->item !== null && $line->stock_lot_id !== null && $line->stockLot?->selling_price !== null);
        $selectedLabelCount = collect($quantity)->sum(fn ($value) => max(0, (int) $value));
    @endphp

    <div class="mx-auto max-w-7xl space-y-5">
        @if (session('status'))
            <div role="status" class="flex items-center gap-2.5 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300"><svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6"/></svg>{{ session('status') }}</div>
        @endif
        @error('printer')<div role="alert" class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300">{{ $message }}</div>@enderror
        @error('quantity')<div role="alert" class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300">{{ $message }}</div>@enderror

        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-400">Receiving {{ $receiving->number }}</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-950 dark:text-white">Print item labels</h1>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ $receiving->stockLocation?->name ?? 'Stock location unavailable' }} · {{ $receiving->received_at->format('d M Y') }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('config.manage')
                    <a href="{{ route('settings.labels') }}" class="inline-flex h-9 items-center gap-2 rounded-md border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 2m6-2a9 9 0 1 1-3-6.7M21 3v6h-6"/></svg>Printer settings</a>
                @endcan
                <a href="{{ route('receivings.show', $receiving) }}" class="inline-flex h-9 items-center gap-2 rounded-md border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6M9 12h12"/></svg>Back to receiving</a>
            </div>
        </header>

        <section aria-label="Label print summary" class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <dl class="grid grid-cols-2 divide-x divide-y divide-slate-200 dark:divide-slate-800 sm:grid-cols-3 sm:divide-y-0">
                <div class="p-4"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Receiving lines</dt><dd class="mt-1.5 text-xl font-bold tabular-nums text-slate-950 dark:text-white">{{ number_format($receiving->lines->count()) }}</dd></div>
                <div class="p-4"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Ready to print</dt><dd class="mt-1.5 text-xl font-bold tabular-nums text-emerald-700 dark:text-emerald-400">{{ number_format($printableLines->count()) }}</dd></div>
                <div class="col-span-2 p-4 sm:col-span-1"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Selected labels</dt><dd class="mt-1.5 text-xl font-bold tabular-nums text-slate-950 dark:text-white">{{ number_format($selectedLabelCount) }}</dd></div>
            </dl>
        </section>

        <div role="note" class="flex items-start gap-3 rounded-md border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:border-sky-900 dark:bg-sky-950/30 dark:text-sky-200">
            <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 11v5m0-8h.01"/></svg>
            <p>Label price is taken from each received stock lot. Missing barcodes are assigned automatically when printing. Lines without a lot or selling price must be corrected before they can be printed.</p>
        </div>

        <form wire:submit="printLabels" class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-1 border-b border-slate-200 px-4 py-3 dark:border-slate-800 sm:px-5"><h2 class="text-sm font-semibold text-slate-950 dark:text-white">Items in this receiving</h2><p class="text-xs text-slate-500 dark:text-slate-400">Choose the number of labels for each eligible lot.</p></div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1120px] text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-400">
                        <tr><th scope="col" class="px-4 py-3">Item</th><th scope="col" class="px-4 py-3">Stock lot</th><th scope="col" class="px-4 py-3">Barcode</th><th scope="col" class="px-4 py-3 text-right">Lot price</th><th scope="col" class="px-4 py-3 text-right">Received</th><th scope="col" class="px-4 py-3 text-right">Labels</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($receiving->lines as $line)
                            @php
                                $isPrintable = $line->item !== null && $line->stock_lot_id !== null && $line->stockLot?->selling_price !== null;
                                $primaryBarcode = $line->item?->barcodes->firstWhere('is_primary', true)?->barcode;
                            @endphp
                            <tr wire:key="receiving-label-line-{{ $line->id }}" class="{{ $isPrintable ? 'hover:bg-slate-50/70 dark:hover:bg-slate-800/30' : 'bg-amber-50/40 dark:bg-amber-950/10' }}">
                                <td class="px-4 py-3.5"><div class="font-semibold text-slate-900 dark:text-white">{{ $line->item?->name ?? 'Item unavailable' }}</div><div class="mt-0.5 font-mono text-xs text-slate-500 dark:text-slate-400">{{ $line->item?->sku ?? 'No SKU' }}</div></td>
                                <td class="px-4 py-3.5">
                                    @if ($line->stockLot)
                                        <div class="font-medium text-slate-800 dark:text-slate-200">{{ $line->stockLot->lot_number }}</div><div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">@if ($line->stockLot->expires_on)Expires {{ $line->stockLot->expires_on->format('d M Y') }}@else No expiry date @endif</div>
                                    @else
                                        <span class="text-xs font-semibold text-amber-800 dark:text-amber-300">No stock lot assigned</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5">
                                    @if ($primaryBarcode)
                                        <code class="rounded bg-slate-100 px-2 py-1 font-mono text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $primaryBarcode }}</code>
                                    @else
                                        <span class="text-xs text-sky-700 dark:text-sky-300">Assigned at print time</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap px-4 py-3.5 text-right font-semibold tabular-nums text-slate-900 dark:text-white">{{ $line->stockLot?->selling_price ?? '—' }}@if ($line->stockLot && $line->stockLot->selling_price === null)<div class="mt-0.5 text-xs font-normal text-amber-700 dark:text-amber-300">Price required</div>@endif</td>
                                <td class="whitespace-nowrap px-4 py-3.5 text-right tabular-nums text-slate-600 dark:text-slate-300">{{ number_format((float) $line->quantity, 3) }}</td>
                                <td class="px-4 py-3.5 text-right">
                                    <label for="label-quantity-{{ $line->id }}" class="sr-only">Labels for {{ $line->item?->name ?? 'item' }}</label>
                                    <input wire:model.live="quantity.{{ $line->id }}" id="label-quantity-{{ $line->id }}" type="number" min="0" step="1" inputmode="numeric" @disabled(! $isPrintable) class="h-10 w-24 rounded-md border border-slate-300 bg-white px-3 text-right text-sm font-semibold tabular-nums text-slate-900 shadow-sm outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 dark:border-slate-700 dark:bg-slate-950 dark:text-white dark:disabled:bg-slate-800 dark:disabled:text-slate-500">
                                    @error("quantity.{$line->id}")<p class="mt-1 max-w-40 text-left text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>@enderror
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-14 text-center"><p class="text-sm font-semibold text-slate-900 dark:text-white">No item lines in this receiving</p><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">There are no labels to print.</p></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <footer class="flex flex-col gap-3 border-t border-slate-200 bg-slate-50/70 px-4 py-3 dark:border-slate-800 dark:bg-slate-950/40 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <p class="text-xs text-slate-500 dark:text-slate-400">Selected quantities are label counts, not stock quantities.</p>
                <button type="submit" wire:loading.attr="disabled" wire:target="printLabels" @disabled($selectedLabelCount < 1) class="inline-flex h-10 items-center justify-center gap-2 self-start rounded-md bg-emerald-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-emerald-600 dark:hover:bg-emerald-500 sm:self-auto">
                    <svg wire:loading.remove wire:target="printLabels" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 8V3h10v5M7 17H5a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2m-10-4h10v8H7z"/></svg>
                    <svg wire:loading wire:target="printLabels" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"/></svg>
                    <span wire:loading.remove wire:target="printLabels">Print {{ number_format($selectedLabelCount) }} labels</span>
                    <span wire:loading wire:target="printLabels">Sending to printer…</span>
                </button>
            </footer>
        </form>
    </div>
</div>
