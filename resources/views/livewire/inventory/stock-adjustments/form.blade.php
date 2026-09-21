<div>
    @section('title', 'Adjust stock')

    @php
        $fieldClass = 'h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-950 shadow-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white';
        $adjustmentAmount = is_numeric($quantity) ? number_format((float) $quantity, 3, '.', ',') : '0.000';
    @endphp

    <div class="mx-auto max-w-5xl space-y-6">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-medium text-emerald-700 dark:text-emerald-400">Inventory</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 dark:text-white">Adjust stock</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Record a stock correction with a clear reason for the inventory ledger.</p>
            </div>
            @if ($stock_location_id)
                <a href="{{ route('stock-locations.stock', $stock_location_id) }}" class="inline-flex h-9 items-center gap-1.5 self-start text-sm font-semibold text-slate-600 hover:text-emerald-700 dark:text-slate-300 dark:hover:text-emerald-300 sm:self-auto">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6M9 12h12" /></svg>
                    Back to location stock
                </a>
            @endif
        </header>

        <form wire:submit="save" class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(260px,0.68fr)] lg:items-start">
            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800 sm:px-6">
                    <h3 class="text-base font-semibold text-slate-950 dark:text-white">Adjustment details</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Select the location and item, then enter the physical difference.</p>
                </div>

                <div class="space-y-5 p-5 sm:p-6">
                    @if ($stockLocations->isEmpty())
                        <div role="alert" class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">You do not have access to a stock location. Ask an administrator to assign one before making an adjustment.</div>
                    @endif

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="stock_location_id" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Stock location <span class="text-rose-600">*</span></label>
                            <select wire:model.live="stock_location_id" id="stock_location_id" required class="{{ $fieldClass }}">
                                <option value="">Select a location</option>
                                @foreach ($stockLocations as $location)
                                    <option value="{{ $location->id }}">{{ $location->name }}{{ $location->is_default ? ' · Default' : '' }}</option>
                                @endforeach
                            </select>
                            @error('stock_location_id') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="item_id" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Item <span class="text-rose-600">*</span></label>
                            <select wire:model.live="item_id" id="item_id" required class="{{ $fieldClass }}">
                                <option value="">Select an item</option>
                                @foreach ($items as $item)
                                    <option value="{{ $item->id }}">{{ $item->name }} ({{ $item->sku }})</option>
                                @endforeach
                            </select>
                            @error('item_id') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <fieldset>
                        <legend class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300">Movement <span class="text-rose-600">*</span></legend>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="flex cursor-pointer items-center gap-3 rounded-lg border p-3.5 transition {{ $direction === 'increase' ? 'border-emerald-600 bg-emerald-50/70 dark:border-emerald-500 dark:bg-emerald-950/25' : 'border-slate-200 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800/60' }}">
                                <input wire:model.live="direction" type="radio" name="direction" value="increase" class="h-4 w-4 accent-emerald-600">
                                <span class="grid h-9 w-9 place-items-center rounded-md bg-emerald-100 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg></span>
                                <span><span class="block text-sm font-semibold text-slate-900 dark:text-white">Increase</span><span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">Add to on-hand stock</span></span>
                            </label>
                            <label class="flex cursor-pointer items-center gap-3 rounded-lg border p-3.5 transition {{ $direction === 'decrease' ? 'border-rose-600 bg-rose-50/70 dark:border-rose-500 dark:bg-rose-950/25' : 'border-slate-200 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800/60' }}">
                                <input wire:model.live="direction" type="radio" name="direction" value="decrease" class="h-4 w-4 accent-rose-600">
                                <span class="grid h-9 w-9 place-items-center rounded-md bg-rose-100 text-rose-800 dark:bg-rose-500/10 dark:text-rose-300"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M5 12h14" /></svg></span>
                                <span><span class="block text-sm font-semibold text-slate-900 dark:text-white">Decrease</span><span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">Remove from on-hand stock</span></span>
                            </label>
                        </div>
                        @error('direction') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </fieldset>

                    <div>
                        <label for="quantity" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Quantity <span class="text-rose-600">*</span></label>
                        <div class="relative max-w-sm">
                            <input wire:model.live.debounce.300ms="quantity" id="quantity" type="text" inputmode="decimal" autocomplete="off" placeholder="0.000" required aria-describedby="quantity-help" class="{{ $fieldClass }} pr-20 text-right font-mono tabular-nums">
                            <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs font-medium text-slate-400">units</span>
                        </div>
                        <p id="quantity-help" class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">Enter a positive quantity. Use decimals for fractional stock.</p>
                        @error('quantity') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="note" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Reason <span class="text-rose-600">*</span></label>
                        <textarea wire:model.live.debounce.500ms="note" id="note" rows="3" maxlength="255" required placeholder="For example: damaged in storage, recount correction, or found during cycle count" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white"></textarea>
                        <div class="mt-1.5 flex items-start justify-between gap-3"><p class="text-xs text-slate-500 dark:text-slate-400">This reason is saved with the inventory movement.</p><span class="shrink-0 text-xs tabular-nums text-slate-400">{{ mb_strlen($note) }}/255</span></div>
                        @error('note') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <footer class="flex flex-col-reverse gap-3 border-t border-slate-200 bg-slate-50/70 px-5 py-4 sm:flex-row sm:items-center sm:justify-end dark:border-slate-800 dark:bg-slate-950/40 sm:px-6">
                    <a href="{{ $stock_location_id ? route('stock-locations.stock', $stock_location_id) : route('stock-locations.index') }}" class="inline-flex h-10 items-center justify-center rounded-md px-4 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">Cancel</a>
                    <button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-emerald-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-emerald-600 dark:hover:bg-emerald-500">
                        <svg wire:loading.remove wire:target="save" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7M4 5h12l4 4v10H4z" /></svg>
                        <svg wire:loading wire:target="save" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z" /></svg>
                        <span wire:loading.remove wire:target="save">Record adjustment</span>
                        <span wire:loading wire:target="save">Recording…</span>
                    </button>
                </footer>
            </section>

            <aside class="space-y-4 lg:sticky lg:top-20">
                <section class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-4 py-3.5 dark:border-slate-800"><h3 class="text-sm font-semibold text-slate-900 dark:text-white">Movement preview</h3><p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Review the stock change before recording it.</p></div>
                    <div class="p-4">
                        <div class="flex items-center justify-between gap-4 rounded-md {{ $direction === 'decrease' ? 'bg-rose-50 dark:bg-rose-500/10' : 'bg-emerald-50 dark:bg-emerald-500/10' }} px-4 py-3.5">
                            <span class="text-sm font-medium {{ $direction === 'decrease' ? 'text-rose-800 dark:text-rose-300' : 'text-emerald-800 dark:text-emerald-300' }}">{{ $direction === 'decrease' ? 'Stock removed' : 'Stock added' }}</span>
                            <span class="font-mono text-xl font-bold tabular-nums {{ $direction === 'decrease' ? 'text-rose-800 dark:text-rose-300' : 'text-emerald-800 dark:text-emerald-300' }}">{{ $quantity !== '' ? ($direction === 'decrease' ? '−' : '+').$adjustmentAmount : '—' }}</span>
                        </div>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Location</dt><dd class="truncate text-right font-medium text-slate-800 dark:text-slate-200">{{ $stockLocations->firstWhere('id', $stock_location_id)?->name ?? 'Not selected' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Item</dt><dd class="truncate text-right font-medium text-slate-800 dark:text-slate-200">{{ $items->firstWhere('id', $item_id)?->name ?? 'Not selected' }}</dd></div>
                            <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Reason</dt><dd class="max-w-[65%] break-words text-right text-slate-700 dark:text-slate-300">{{ $note ?: 'Add a reason' }}</dd></div>
                        </dl>
                    </div>
                </section>
                <div class="flex gap-3 rounded-lg border border-amber-200 bg-amber-50/80 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                    <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 4h.01M10.3 3.9 2.7 17a2 2 0 001.7 3h15.2a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z" /></svg>
                    <p>Adjustments are recorded in the inventory ledger and affect the selected location immediately.</p>
                </div>
            </aside>
        </form>
    </div>
</div>
