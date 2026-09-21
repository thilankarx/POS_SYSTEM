<div>
    @section('title', $itemKit ? 'Edit item kit' : 'New item kit')

    <div class="max-w-7xl space-y-6">
        <header class="flex flex-col gap-4 border-b border-slate-200 pb-4 dark:border-slate-800 sm:flex-row sm:items-end sm:justify-between">
            <div><p class="text-xs font-semibold uppercase text-emerald-700 dark:text-emerald-400">Catalog</p><h2 class="mt-1 text-2xl font-bold text-slate-950 dark:text-white">{{ $itemKit ? 'Edit item kit' : 'New item kit' }}</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Build a bundle from active items and review its selling total.</p></div>
            <a href="{{ route('item-kits.index') }}" class="inline-flex h-10 items-center gap-2 self-start rounded-md border border-slate-300 bg-white px-3.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 sm:self-auto"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M15 19l-7-7 7-7"/></svg>Back to item kits</a>
        </header>

        <form wire:submit="save" class="space-y-4">
            <div class="grid gap-4 xl:grid-cols-3 xl:items-start">
                <div class="space-y-4 xl:col-span-2">
                    <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
                        <div class="mb-5 flex items-center gap-3"><span class="grid h-9 w-9 place-items-center rounded-md bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M4 7h6l2 2h8v10H4V7zm0 0V5h6l2 2"/></svg></span><div><h3 class="text-sm font-semibold">Kit details</h3><p class="text-xs text-slate-500">Reference and customer-facing description</p></div></div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div><label for="kit_number" class="mb-1.5 block text-sm font-semibold">Kit number <span class="text-red-600">*</span></label><input wire:model="kit_number" id="kit_number" type="text" maxlength="255" autocomplete="off" placeholder="e.g. KIT-001" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 font-mono text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">@error('kit_number')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                            <div><label for="name" class="mb-1.5 block text-sm font-semibold">Kit name <span class="text-red-600">*</span></label><input wire:model="name" id="name" type="text" maxlength="255" autocomplete="off" placeholder="e.g. Starter plumbing set" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">@error('name')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                            <div class="sm:col-span-2"><label for="description" class="mb-1.5 block text-sm font-semibold">Description</label><textarea wire:model="description" id="description" rows="3" placeholder="Optional bundle description" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950"></textarea>@error('description')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                        </div>
                    </section>

                    <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
                        <div class="mb-5 flex items-center gap-3"><span class="grid h-9 w-9 place-items-center rounded-md bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 3v18M5 8h14M5 16h14"/></svg></span><div><h3 class="text-sm font-semibold">Pricing and receipt</h3><p class="text-xs text-slate-500">Discount behavior and printed lines</p></div></div>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div><label for="discount_value" class="mb-1.5 block text-sm font-semibold">Discount</label><input wire:model.live.debounce.400ms="discount_value" id="discount_value" type="text" inputmode="decimal" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-right text-sm tabular-nums dark:border-slate-700 dark:bg-slate-950">@error('discount_value')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                            <div><label for="discount_type" class="mb-1.5 block text-sm font-semibold">Discount type</label><select wire:model.live="discount_type" id="discount_type" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="percent">Percentage</option><option value="fixed">Fixed amount</option></select></div>
                            <div><label for="price_option" class="mb-1.5 block text-sm font-semibold">Pricing output</label><select wire:model.live="price_option" id="price_option" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="kit">Kit line</option><option value="components">Components only</option><option value="both">Kit + components</option></select></div>
                            <div><label for="print_option" class="mb-1.5 block text-sm font-semibold">Receipt output</label><select wire:model.live="print_option" id="print_option" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="all">Show all lines</option><option value="kit_only">Show kit only</option></select></div>
                        </div>
                    </section>

                    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 dark:border-slate-800 sm:px-5"><div><h3 class="text-sm font-semibold">Component items</h3><p class="mt-0.5 text-xs text-slate-500">{{ count($items) }} {{ str('line')->plural(count($items)) }}</p></div><button type="button" wire:click="addItem" class="inline-flex h-9 items-center gap-1.5 rounded-md border border-slate-300 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>Add item</button></div>
                        @error('items')<p class="border-b border-red-200 bg-red-50 px-4 py-3 text-sm text-red-600 dark:border-red-900 dark:bg-red-950/30">{{ $message }}</p>@enderror
                        <div class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($items as $index => $row)
                                @php $selectedItem = $availableItems->firstWhere('id', (int) ($row['item_id'] ?? 0)); @endphp
                                <div wire:key="kit-item-{{ $index }}" class="p-4 sm:p-5">
                                    <div class="grid gap-3 sm:grid-cols-[2rem_minmax(0,1fr)_7rem_2.5rem] sm:items-start">
                                        <div class="hidden h-10 items-center justify-center font-mono text-xs font-semibold text-slate-400 sm:flex">{{ $index + 1 }}</div>
                                        <div><label for="kit_item_{{ $index }}" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 sm:sr-only">Component {{ $index + 1 }}</label><select wire:model.live="items.{{ $index }}.item_id" id="kit_item_{{ $index }}" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">Select an active item</option>@foreach ($availableItems as $option)<option value="{{ $option->id }}">{{ $option->name }} · {{ $option->sku }} · {{ $option->has_multiple_prices ? 'multiple prices' : ($option->current_price !== null ? \App\Support\Money\Money::of($option->current_price) : 'no price') }}</option>@endforeach</select>@error("items.{$index}.item_id")<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror @if ($selectedItem)<div class="mt-1.5 flex flex-wrap gap-2 text-xs"><span class="text-slate-500">{{ $selectedItem->sku }}</span>@if ($selectedItem->has_multiple_prices)<span class="font-medium text-amber-700 dark:text-amber-400">Multiple lot prices</span>@elseif ($selectedItem->current_price === null)<span class="font-medium text-red-600">No selling price</span>@else<span class="font-medium text-slate-700 dark:text-slate-300">{{ \App\Support\Money\Money::of($selectedItem->current_price) }} each</span>@endif</div>@endif</div>
                                        <div><label for="kit_qty_{{ $index }}" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 sm:sr-only">Quantity</label><input wire:model.live.debounce.400ms="items.{{ $index }}.quantity" id="kit_qty_{{ $index }}" type="text" inputmode="decimal" placeholder="Qty" aria-label="Quantity for component {{ $index + 1 }}" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-right text-sm tabular-nums dark:border-slate-700 dark:bg-slate-950">@error("items.{$index}.quantity")<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                                        <button type="button" wire:click="removeItem({{ $index }})" title="Remove component" aria-label="Remove component {{ $index + 1 }}" class="grid h-10 w-10 place-items-center rounded-md text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/40"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 7h12m-10 0l1 13h6l1-13M9 7V4h6v3"/></svg></button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                </div>

                <aside class="space-y-4 xl:sticky xl:top-20">
                    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-800"><h3 class="text-sm font-semibold">Pricing review</h3></div>
                        <dl class="divide-y divide-slate-100 text-sm dark:divide-slate-800">
                            <div class="flex justify-between gap-4 px-4 py-3"><dt class="text-slate-500">Selected items</dt><dd class="font-semibold tabular-nums">{{ $preview['selectedCount'] }}</dd></div>
                            <div class="flex justify-between gap-4 px-4 py-3"><dt class="text-slate-500">Component total</dt><dd class="font-semibold tabular-nums">{{ $preview['componentTotal'] }}</dd></div>
                            <div class="flex justify-between gap-4 px-4 py-3"><dt class="text-slate-500">Discount</dt><dd class="font-semibold tabular-nums text-emerald-700 dark:text-emerald-400">-{{ $preview['discount'] }}</dd></div>
                            <div class="flex justify-between gap-4 px-4 py-3"><dt class="font-semibold">Kit total</dt><dd class="text-lg font-bold tabular-nums">{{ $preview['kitTotal'] }}</dd></div>
                        </dl>
                        @if ($preview['unpricedCount'] > 0 || $preview['multiplePriceCount'] > 0)
                            <div class="space-y-2 border-t border-slate-200 p-4 text-xs dark:border-slate-800">
                                @if ($preview['unpricedCount'] > 0)<p class="rounded-md bg-red-50 px-3 py-2 font-medium text-red-700 dark:bg-red-950/30 dark:text-red-300">{{ $preview['unpricedCount'] }} selected {{ str('item')->plural($preview['unpricedCount']) }} without a selling price</p>@endif
                                @if ($preview['multiplePriceCount'] > 0)<p class="rounded-md bg-amber-50 px-3 py-2 font-medium text-amber-700 dark:bg-amber-950/30 dark:text-amber-300">{{ $preview['multiplePriceCount'] }} selected {{ str('item')->plural($preview['multiplePriceCount']) }} with multiple lot prices</p>@endif
                            </div>
                        @endif
                    </section>
                    <section class="rounded-lg border border-slate-200 bg-white p-4 text-sm shadow-sm dark:border-slate-800 dark:bg-slate-900"><h3 class="font-semibold">Output summary</h3><dl class="mt-3 space-y-2"><div class="flex justify-between gap-3"><dt class="text-slate-500">Pricing</dt><dd class="text-right font-medium">{{ ['kit' => 'Kit line', 'components' => 'Components', 'both' => 'Kit + components'][$price_option] ?? ucfirst($price_option) }}</dd></div><div class="flex justify-between gap-3"><dt class="text-slate-500">Receipt</dt><dd class="text-right font-medium">{{ $print_option === 'kit_only' ? 'Kit only' : 'All lines' }}</dd></div></dl></section>
                </aside>
            </div>

            <section class="flex flex-col-reverse gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:flex-row sm:items-center sm:justify-end"><a href="{{ route('item-kits.index') }}" class="inline-flex h-10 items-center justify-center px-3 text-sm font-semibold text-slate-600 dark:text-slate-300">Cancel</a><button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex h-10 min-w-36 items-center justify-center rounded-md bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:opacity-60"><span wire:loading.remove wire:target="save">{{ $itemKit ? 'Save changes' : 'Create kit' }}</span><span wire:loading wire:target="save">Saving&hellip;</span></button></section>
        </form>
    </div>
</div>
