<div>
    @section('title', 'Transfer stock')

    @php
        $fieldClass = 'h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-950 shadow-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white';
        $transferAmount = is_numeric($quantity) && (float) $quantity > 0 ? number_format((float) $quantity, 3, '.', ',') : null;
        $sameLocation = $from_location_id && $to_location_id && (int) $from_location_id === (int) $to_location_id;
        $insufficientStock = $transferAmount !== null && $sourceQuantity !== null && (float) $quantity > (float) $sourceQuantity;
    @endphp

    <div class="mx-auto max-w-5xl space-y-6">
        <header>
            <p class="text-sm font-medium text-emerald-700 dark:text-emerald-400">Inventory</p>
            <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 dark:text-white">Transfer stock</h2>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Move stock between locations and keep both inventory ledgers in sync.</p>
        </header>

        <form wire:submit="save" class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(270px,0.68fr)] lg:items-start">
            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800 sm:px-6">
                    <h3 class="text-base font-semibold text-slate-950 dark:text-white">Transfer details</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Choose a stocked item, source, destination, and quantity.</p>
                </div>
                <div class="space-y-5 p-5 sm:p-6">
                    @if ($stockLocations->count() < 2)
                        <div role="alert" class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">A transfer needs two locations. Ask an administrator to assign you access to another stock location.</div>
                    @endif

                    <div>
                        <label for="item_id" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Item <span class="text-rose-600">*</span></label>
                        <select wire:model.live="item_id" id="item_id" required class="{{ $fieldClass }}">
                            <option value="">Select a stocked item</option>
                            @foreach ($items as $itemOption)
                                <option value="{{ $itemOption->id }}">{{ $itemOption->name }} ({{ $itemOption->sku }})</option>
                            @endforeach
                        </select>
                        <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">Services and non-stock items are excluded.</p>
                        @error('item_id') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_2.5rem_minmax(0,1fr)] sm:items-end">
                        <div>
                            <label for="from_location_id" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">From location <span class="text-rose-600">*</span></label>
                            <select wire:model.live="from_location_id" id="from_location_id" required class="{{ $fieldClass }}">
                                <option value="">Select source</option>
                                @foreach ($stockLocations as $location)
                                    <option value="{{ $location->id }}">{{ $location->name }}{{ $location->is_default ? ' · Default' : '' }}</option>
                                @endforeach
                            </select>
                            @error('from_location_id') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="hidden h-11 place-items-center text-slate-400 sm:grid" aria-hidden="true"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6" /></svg></div>
                        <div>
                            <label for="to_location_id" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">To location <span class="text-rose-600">*</span></label>
                            <select wire:model.live="to_location_id" id="to_location_id" required class="{{ $fieldClass }}">
                                <option value="">Select destination</option>
                                @foreach ($stockLocations as $location)
                                    <option value="{{ $location->id }}">{{ $location->name }}{{ $location->is_default ? ' · Default' : '' }}</option>
                                @endforeach
                            </select>
                            @error('to_location_id') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    @if ($sameLocation)
                        <p role="alert" class="rounded-md border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-300">Choose two different locations to make a transfer.</p>
                    @endif

                    <div class="max-w-sm">
                        <label for="quantity" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Quantity to transfer <span class="text-rose-600">*</span></label>
                        <div class="relative">
                            <input wire:model.live.debounce.300ms="quantity" id="quantity" type="text" inputmode="decimal" autocomplete="off" placeholder="0.000" required class="{{ $fieldClass }} pr-20 text-right font-mono tabular-nums">
                            <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs font-medium text-slate-400">units</span>
                        </div>
                        @error('quantity') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="note" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Transfer note <span class="font-normal text-slate-500">(optional)</span></label>
                        <textarea wire:model="note" id="note" rows="3" maxlength="255" placeholder="For example: replenish shop floor or rebalance stock" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white"></textarea>
                        <div class="mt-1.5 flex justify-between gap-3"><span class="text-xs text-slate-500 dark:text-slate-400">Saved with both sides of the transfer.</span><span class="shrink-0 text-xs tabular-nums text-slate-400">{{ mb_strlen($note) }}/255</span></div>
                        @error('note') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <footer class="flex flex-col-reverse gap-3 border-t border-slate-200 bg-slate-50/70 px-5 py-4 sm:flex-row sm:items-center sm:justify-end dark:border-slate-800 dark:bg-slate-950/40 sm:px-6">
                    <a href="{{ $from_location_id ? route('stock-locations.stock', $from_location_id) : route('stock-locations.index') }}" class="inline-flex h-10 items-center justify-center rounded-md px-4 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">Cancel</a>
                    <button type="submit" wire:loading.attr="disabled" wire:target="save" @disabled($stockLocations->count() < 2 || $sameLocation) class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-emerald-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-emerald-600 dark:hover:bg-emerald-500">
                        <svg wire:loading.remove wire:target="save" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 6" /></svg>
                        <svg wire:loading wire:target="save" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z" /></svg>
                        <span wire:loading.remove wire:target="save">Record transfer</span>
                        <span wire:loading wire:target="save">Transferring…</span>
                    </button>
                </footer>
            </section>

            <aside class="space-y-4 lg:sticky lg:top-20">
                <section class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <div class="border-b border-slate-200 px-4 py-3.5 dark:border-slate-800"><h3 class="text-sm font-semibold text-slate-900 dark:text-white">Transfer preview</h3><p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">The same quantity moves between both locations.</p></div>
                    <div class="space-y-3 p-4">
                        <div class="flex items-center justify-between gap-3 rounded-md bg-rose-50 px-3.5 py-3 dark:bg-rose-500/10">
                            <div class="min-w-0"><p class="text-[11px] font-semibold uppercase text-rose-700 dark:text-rose-300">Leaves</p><p class="mt-0.5 truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $stockLocations->firstWhere('id', $from_location_id)?->name ?? 'Source location' }}</p><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">On hand: {{ $sourceQuantity !== null ? number_format((float) $sourceQuantity, 3, '.', ',') : '—' }}</p></div>
                            <span class="shrink-0 font-mono text-lg font-bold tabular-nums text-rose-800 dark:text-rose-300">{{ $transferAmount ? '−'.$transferAmount : '—' }}</span>
                        </div>
                        <div class="flex justify-center text-slate-400" aria-hidden="true"><svg class="h-4 w-4 rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6" /></svg></div>
                        <div class="flex items-center justify-between gap-3 rounded-md bg-emerald-50 px-3.5 py-3 dark:bg-emerald-500/10">
                            <div class="min-w-0"><p class="text-[11px] font-semibold uppercase text-emerald-700 dark:text-emerald-300">Arrives</p><p class="mt-0.5 truncate text-sm font-semibold text-slate-900 dark:text-white">{{ $stockLocations->firstWhere('id', $to_location_id)?->name ?? 'Destination location' }}</p><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">On hand: {{ $destinationQuantity !== null ? number_format((float) $destinationQuantity, 3, '.', ',') : '—' }}</p></div>
                            <span class="shrink-0 font-mono text-lg font-bold tabular-nums text-emerald-800 dark:text-emerald-300">{{ $transferAmount ? '+'.$transferAmount : '—' }}</span>
                        </div>
                    </div>
                </section>

                @if ($selectedItem)
                    <section class="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Selected item</h3>
                        <p class="mt-2 text-sm font-medium text-slate-800 dark:text-slate-200">{{ $selectedItem->name }}</p>
                        <p class="mt-0.5 font-mono text-xs text-slate-500 dark:text-slate-400">{{ $selectedItem->sku }}</p>
                        @if ($sourceQuantity !== null)
                            <div class="mt-3 border-t border-slate-100 pt-3 dark:border-slate-800">
                                @if ($insufficientStock)
                                    <p class="flex items-start gap-2 text-xs leading-5 text-rose-700 dark:text-rose-300"><svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 4h.01M10.3 3.9 2.7 17a2 2 0 001.7 3h15.2a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z" /></svg>Quantity is above the source on-hand balance. Reduce the amount or choose another source.</p>
                                @else
                                    <p class="text-xs leading-5 text-slate-500 dark:text-slate-400">The transfer is limited to the source location’s current on-hand quantity.</p>
                                @endif
                            </div>
                        @endif
                    </section>
                @endif
            </aside>
        </form>
    </div>
</div>
