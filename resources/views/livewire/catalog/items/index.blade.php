<div>
    @section('title', 'Items')
    @php
        $qty = static fn ($value) => rtrim(rtrim(number_format((float) ($value ?? 0), 3, '.', ''), '0'), '.');
        $hasFilters = $search !== '' || $category !== '' || $supplier !== '' || $stockType !== '' || $status !== '' || $sort !== 'name' || $businessType !== $defaultBusinessType;
    @endphp

    <div class="space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-950 dark:text-white">Items</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Manage products, services, pricing, and inventory settings.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('items.import')
                    <a href="{{ route('items.import') }}" class="inline-flex h-10 items-center gap-2 rounded-md border border-slate-300 bg-white px-3.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg> Import
                    </a>
                @endcan
                @can('create', \App\Domain\Catalog\Models\Item::class)
                    <a href="{{ route('items.create') }}" class="inline-flex h-10 items-center gap-2 rounded-md bg-emerald-600 px-3.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg> New item
                    </a>
                @endcan
            </div>
        </header>

        @if ($bulkEditStatus)
            <div class="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm font-medium text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-300">{{ $bulkEditStatus }}</div>
        @endif

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <dl class="grid grid-cols-2 divide-x divide-y divide-slate-200 dark:divide-slate-800 lg:grid-cols-4 lg:divide-y-0">
                @foreach ([['Matching items', $summary['total'], 'Current filters'], ['Active', $summary['active'], 'Available for use'], ['Stocked', $summary['stocked'], 'Inventory tracked'], ['Services', $summary['services'], 'Non-stock items']] as [$label, $value, $note])
                    <div class="p-4 sm:p-5"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">{{ $label }}</dt><dd class="mt-2 text-2xl font-bold tabular-nums {{ $label === 'Active' ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-950 dark:text-white' }}">{{ number_format($value) }}</dd><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $note }}</p></div>
                @endforeach
            </dl>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-[minmax(16rem,1.4fr)_12rem_12rem_10rem_11rem_12rem_10rem_auto] 2xl:items-end">
                <div class="min-w-0"><label for="item-search" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Search</label><div class="relative"><svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M21 21l-4.35-4.35m2.35-5.65a8 8 0 11-16 0 8 8 0 0116 0z"/></svg><input wire:model.live.debounce.300ms="search" id="item-search" type="search" placeholder="Name, SKU, or barcode" class="h-10 w-full rounded-md border border-slate-300 bg-white pl-9 pr-3 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950"></div></div>
                <div><label for="item-category" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Category</label><select wire:model.live="category" id="item-category" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">All categories</option>@foreach ($categories as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach</select></div>
                <div><label for="item-supplier" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Supplier</label><select wire:model.live="supplier" id="item-supplier" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">All suppliers</option>@foreach ($suppliers as $option)<option value="{{ $option->id }}">{{ $option->company_name }}</option>@endforeach</select></div>
                <div><label for="item-type" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Item type</label><select wire:model.live="stockType" id="item-type" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">All types</option><option value="stocked">Stocked</option><option value="service">Service</option></select></div>
                <div><label for="item-status" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Status</label><select wire:model.live="status" id="item-status" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
                <div><label for="item-business" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Business use</label><select wire:model.live="businessType" id="item-business" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm capitalize dark:border-slate-700 dark:bg-slate-950">@foreach ($businessTypes as $type)<option value="{{ $type }}">{{ ucfirst($type) }}</option>@endforeach<option value="all">All types</option></select></div>
                <div><label for="item-sort" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Sort</label><select wire:model.live="sort" id="item-sort" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="name">Name</option><option value="sku">SKU</option><option value="newest">Newest</option></select></div>
                @if ($hasFilters)<button type="button" wire:click="clearFilters" class="h-10 rounded-md px-3 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">Clear</button>@endif
            </div>
        </section>

        @can('items.bulk_edit')
            @if ($selected !== [])
                <section class="rounded-lg border border-emerald-200 bg-emerald-50/60 p-4 shadow-sm dark:border-emerald-900 dark:bg-emerald-950/20 sm:p-5">
                    <div class="mb-4 flex items-center justify-between gap-3"><div><h3 class="text-sm font-semibold">Bulk edit</h3><p class="mt-0.5 text-xs text-slate-500">{{ count($selected) }} {{ str('item')->plural(count($selected)) }} selected</p></div><button wire:click="clearSelection" class="text-sm font-semibold text-slate-600 dark:text-slate-300">Clear selection</button></div>
                    @error('bulkEdit')<p class="mb-3 text-sm text-red-600">{{ $message }}</p>@enderror
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <div><label class="mb-2 flex gap-2 text-sm font-medium"><input wire:model.live="bulkCategoryApply" type="checkbox" class="rounded text-emerald-600">Set category</label><select wire:model="bulkCategoryId" @disabled(! $bulkCategoryApply) class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm disabled:opacity-50 dark:border-slate-700 dark:bg-slate-950"><option value="">None</option>@foreach ($categories as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach</select></div>
                        <div><label class="mb-2 flex gap-2 text-sm font-medium"><input wire:model.live="bulkSupplierApply" type="checkbox" class="rounded text-emerald-600">Set supplier</label><select wire:model="bulkSupplierId" @disabled(! $bulkSupplierApply) class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm disabled:opacity-50 dark:border-slate-700 dark:bg-slate-950"><option value="">None</option>@foreach ($suppliers as $option)<option value="{{ $option->id }}">{{ $option->company_name }}</option>@endforeach</select></div>
                        <div><label class="mb-2 flex gap-2 text-sm font-medium"><input wire:model.live="bulkTaxCategoryApply" type="checkbox" class="rounded text-emerald-600">Set tax category</label><select wire:model="bulkTaxCategoryId" @disabled(! $bulkTaxCategoryApply) class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm disabled:opacity-50 dark:border-slate-700 dark:bg-slate-950"><option value="">None</option>@foreach ($taxCategories as $option)<option value="{{ $option->id }}">{{ $option->name }}</option>@endforeach</select></div>
                        <div><label class="mb-2 flex gap-2 text-sm font-medium"><input wire:model.live="bulkActiveApply" type="checkbox" class="rounded text-emerald-600">Set status</label><select wire:model="bulkActiveValue" @disabled(! $bulkActiveApply) class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm disabled:opacity-50 dark:border-slate-700 dark:bg-slate-950"><option value="1">Active</option><option value="0">Inactive</option></select></div>
                    </div>
                    <div class="mt-4 text-right"><button wire:click="applyBulkEdit" wire:loading.attr="disabled" class="h-10 rounded-md bg-slate-950 px-4 text-sm font-semibold text-white dark:bg-white dark:text-slate-950">Apply changes</button></div>
                </section>
            @endif
        @endcan

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900" wire:loading.class="opacity-60" wire:target="search,category,supplier,stockType,status,businessType,sort">
            <div class="hidden overflow-x-auto lg:block">
                <table class="w-full min-w-[1040px] text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-950/50">@can('items.bulk_edit')<th class="w-12 px-4 py-3"><span class="sr-only">Select</span></th>@endcan<th class="px-4 py-3">Item</th><th class="px-4 py-3">Category / supplier</th><th class="px-4 py-3">Inventory</th><th class="px-4 py-3 text-right">Selling price</th><th class="px-4 py-3">Status</th><th class="w-24 px-3 py-3"></th></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($items as $item)
                            <tr wire:key="item-{{ $item->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-800/50 {{ $item->is_active ? '' : 'bg-slate-50/60 dark:bg-slate-950/20' }}">
                                @can('items.bulk_edit')<td class="px-4 py-3 align-top"><input wire:model.live="selected" value="{{ $item->id }}" type="checkbox" aria-label="Select {{ $item->name }}" class="rounded text-emerald-600"></td>@endcan
                                <td class="px-4 py-3"><div class="font-semibold text-slate-950 dark:text-white">{{ $item->name }}</div><div class="mt-1 font-mono text-xs text-slate-500">{{ $item->sku }}</div></td>
                                <td class="px-4 py-3"><div>{{ $item->category?->name ?? 'Uncategorized' }}</div><div class="mt-1 text-xs text-slate-500">{{ $item->supplier?->company_name ?? 'No supplier' }}</div></td>
                                <td class="px-4 py-3">@if ($item->movesStock())<div class="font-semibold tabular-nums">{{ $qty($item->stock_on_hand) }} {{ $item->unit_of_measure }}</div><div class="mt-1 flex gap-1">@if ($item->is_serialized)<span class="rounded bg-sky-50 px-1.5 py-0.5 text-xs text-sky-700">Serials</span>@endif @if ($item->has_expiry)<span class="rounded bg-amber-50 px-1.5 py-0.5 text-xs text-amber-700">Expiry</span>@endif</div>@else<span class="font-medium">Service</span><div class="mt-1 text-xs text-slate-500">No inventory</div>@endif</td>
                                <td class="px-4 py-3 text-right">@if ($item->has_multiple_prices)<span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">Multiple prices</span><div class="mt-1 text-xs text-slate-500">Varies by lot</div>@elseif ($item->current_price !== null)<div class="font-semibold tabular-nums">{{ \App\Support\Money\Money::of($item->current_price) }}</div><div class="mt-1 text-xs text-slate-500">{{ $item->movesStock() ? 'Current lot' : 'Standard price' }}</div>@else<span class="font-medium text-red-600">No price</span>@endif</td>
                                <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $item->is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}">{{ $item->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td class="px-3 py-3"><div class="flex justify-end gap-1">@can('update', $item)<a href="{{ route('items.edit', $item) }}" title="Edit item" aria-label="Edit {{ $item->name }}" class="grid h-8 w-8 place-items-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-emerald-700"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M15 5l4 4L10 18l-5 1 1-5 9-9z"/></svg></a>@endcan @can('delete', $item)<button wire:click="delete({{ $item->id }})" wire:confirm="Delete this item?" title="Delete item" aria-label="Delete {{ $item->name }}" class="grid h-8 w-8 place-items-center rounded-md text-slate-400 hover:bg-red-50 hover:text-red-600"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 7h12m-10 0l1 13h6l1-13M9 7V4h6v3"/></svg></button>@endcan</div></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-5 py-14 text-center"><h3 class="text-sm font-semibold">No items match this view</h3><p class="mt-1 text-sm text-slate-500">Adjust the filters or add a new catalog item.</p>@if ($hasFilters)<button wire:click="clearFilters" class="mt-3 text-sm font-semibold text-emerald-700">Clear filters</button>@endif</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-slate-200 dark:divide-slate-800 lg:hidden">
                @forelse ($items as $item)
                    <article wire:key="item-card-{{ $item->id }}" class="p-4 {{ $item->is_active ? '' : 'bg-slate-50/70 dark:bg-slate-950/20' }}">
                        <div class="flex items-start gap-3">@can('items.bulk_edit')<input wire:model.live="selected" value="{{ $item->id }}" type="checkbox" aria-label="Select {{ $item->name }}" class="mt-1 rounded text-emerald-600">@endcan
                            <div class="min-w-0 flex-1"><div class="flex justify-between gap-3"><div class="min-w-0"><h3 class="truncate text-sm font-semibold">{{ $item->name }}</h3><p class="mt-0.5 truncate font-mono text-xs text-slate-500">{{ $item->sku }}</p></div><span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold {{ $item->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $item->is_active ? 'Active' : 'Inactive' }}</span></div>
                                <dl class="mt-3 grid grid-cols-2 gap-3 text-xs"><div><dt class="text-slate-500">Category</dt><dd class="mt-0.5 truncate font-medium">{{ $item->category?->name ?? 'Uncategorized' }}</dd></div><div><dt class="text-slate-500">{{ $item->movesStock() ? 'On hand' : 'Type' }}</dt><dd class="mt-0.5 font-medium">{{ $item->movesStock() ? $qty($item->stock_on_hand).' '.$item->unit_of_measure : 'Service' }}</dd></div><div><dt class="text-slate-500">Supplier</dt><dd class="mt-0.5 truncate font-medium">{{ $item->supplier?->company_name ?? 'No supplier' }}</dd></div><div><dt class="text-slate-500">Selling price</dt><dd class="mt-0.5 font-semibold">@if ($item->has_multiple_prices)<span class="text-amber-700">Multiple prices</span>@elseif ($item->current_price !== null){{ \App\Support\Money\Money::of($item->current_price) }}@else<span class="text-red-600">No price</span>@endif</dd></div></dl>
                                <div class="mt-3 flex justify-end gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">@can('update', $item)<a href="{{ route('items.edit', $item) }}" class="inline-flex h-9 items-center rounded-md border border-slate-300 px-3 text-xs font-semibold dark:border-slate-700">Edit</a>@endcan @can('delete', $item)<button wire:click="delete({{ $item->id }})" wire:confirm="Delete this item?" aria-label="Delete {{ $item->name }}" class="grid h-9 w-9 place-items-center rounded-md text-red-600"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 7h12m-10 0l1 13h6l1-13M9 7V4h6v3"/></svg></button>@endcan</div>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="px-5 py-14 text-center"><h3 class="text-sm font-semibold">No items match this view</h3><p class="mt-1 text-sm text-slate-500">Adjust the filters or add a new catalog item.</p>@if ($hasFilters)<button wire:click="clearFilters" class="mt-3 text-sm font-semibold text-emerald-700">Clear filters</button>@endif</div>
                @endforelse
            </div>
        </section>
        @if ($items->hasPages())<div>{{ $items->links() }}</div>@endif
    </div>
</div>
