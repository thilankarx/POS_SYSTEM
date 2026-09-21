<div>
    @section('title', 'Item Kits')
    @php
        $hasFilters = $search !== '' || $priceOption !== '' || $discount !== '' || $printOption !== '' || $sort !== 'name' || $businessType !== $defaultBusinessType;
        $pricingLabels = ['kit' => 'Kit line', 'components' => 'Components', 'both' => 'Kit + components'];
        $pricingStyles = ['kit' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300', 'components' => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300', 'both' => 'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300'];
    @endphp

    <div class="space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-950 dark:text-white">Item kits</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Manage bundles, component quantities, discounts, and receipt output.</p>
            </div>
            @can('create', \App\Domain\Catalog\Models\ItemKit::class)
                <a href="{{ route('item-kits.create') }}" class="inline-flex h-10 items-center gap-2 self-start rounded-md bg-emerald-600 px-3.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 sm:self-auto">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                    New kit
                </a>
            @endcan
        </header>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <dl class="grid grid-cols-2 divide-x divide-y divide-slate-200 dark:divide-slate-800 lg:grid-cols-4 lg:divide-y-0">
                @foreach ([['Matching kits', $summary['total'], 'Current filters'], ['Components', $summary['components'], 'Total kit lines'], ['Discounted', $summary['discounted'], 'Discount applied'], ['Kit-only receipt', $summary['kitOnly'], 'Components hidden']] as [$label, $value, $note])
                    <div class="p-4 sm:p-5"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">{{ $label }}</dt><dd class="mt-2 text-2xl font-bold tabular-nums {{ $label === 'Discounted' ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-950 dark:text-white' }}">{{ number_format($value) }}</dd><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $note }}</p></div>
                @endforeach
            </dl>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-[minmax(15rem,1.3fr)_12rem_11rem_12rem_12rem_11rem_auto] 2xl:items-end">
                <div class="min-w-0"><label for="kit-search" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Search</label><div class="relative"><svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M21 21l-4.35-4.35m2.35-5.65a8 8 0 11-16 0 8 8 0 0116 0z"/></svg><input wire:model.live.debounce.300ms="search" id="kit-search" type="search" placeholder="Kit, number, or component" class="h-10 w-full rounded-md border border-slate-300 bg-white pl-9 pr-3 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950"></div></div>
                <div><label for="kit-pricing" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Pricing output</label><select wire:model.live="priceOption" id="kit-pricing" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">All methods</option><option value="kit">Kit line</option><option value="components">Components</option><option value="both">Kit + components</option></select></div>
                <div><label for="kit-discount" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Discount</label><select wire:model.live="discount" id="kit-discount" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">Any discount</option><option value="discounted">Applied</option><option value="none">Not applied</option></select></div>
                <div><label for="kit-receipt" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Receipt</label><select wire:model.live="printOption" id="kit-receipt" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">Any output</option><option value="all">All lines</option><option value="kit_only">Kit only</option></select></div>
                <div><label for="kit-business" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Business use</label><select wire:model.live="businessType" id="kit-business" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm capitalize dark:border-slate-700 dark:bg-slate-950">@foreach ($businessTypes as $type)<option value="{{ $type }}">{{ ucfirst($type) }}</option>@endforeach<option value="all">All types</option></select></div>
                <div><label for="kit-sort" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Sort</label><select wire:model.live="sort" id="kit-sort" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="name">Name</option><option value="number">Kit number</option><option value="components">Most components</option><option value="newest">Newest</option></select></div>
                @if ($hasFilters)<button type="button" wire:click="clearFilters" class="h-10 rounded-md px-3 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">Clear</button>@endif
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900" wire:loading.class="opacity-60" wire:target="search,priceOption,discount,printOption,businessType,sort,clearFilters">
            <div class="hidden overflow-x-auto lg:block">
                <table class="w-full min-w-[960px] text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-950/50 dark:text-slate-400"><tr><th class="px-4 py-3 font-semibold">Kit</th><th class="px-4 py-3 font-semibold">Components</th><th class="px-4 py-3 font-semibold">Pricing output</th><th class="px-4 py-3 font-semibold">Discount</th><th class="px-4 py-3 font-semibold">Receipt</th><th class="w-24 px-3 py-3"><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($itemKits as $itemKit)
                            @php
                                $discountApplied = $itemKit->price_option !== 'components' && bccomp((string) $itemKit->discount_value, '0', 4) > 0;
                                $discountLabel = $discountApplied ? ($itemKit->discount_type === 'percent' ? rtrim(rtrim((string) $itemKit->discount_value, '0'), '.').'%' : (string) \App\Support\Money\Money::of($itemKit->discount_value)) : 'None';
                            @endphp
                            <tr wire:key="kit-{{ $itemKit->id }}" class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="px-4 py-3"><div class="font-semibold text-slate-950 dark:text-white">{{ $itemKit->name }}</div><div class="mt-1 flex items-center gap-2"><span class="font-mono text-xs text-slate-500">{{ $itemKit->kit_number }}</span>@if ($itemKit->description)<span class="max-w-56 truncate text-xs text-slate-400">{{ $itemKit->description }}</span>@endif</div></td>
                                <td class="px-4 py-3"><div class="font-semibold tabular-nums text-slate-950 dark:text-white">{{ number_format($itemKit->items_count) }}</div><div class="mt-1 text-xs text-slate-500">{{ str('component')->plural($itemKit->items_count) }}</div></td>
                                <td class="px-4 py-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $pricingStyles[$itemKit->price_option] ?? 'bg-slate-100 text-slate-600' }}">{{ $pricingLabels[$itemKit->price_option] ?? ucfirst($itemKit->price_option) }}</span></td>
                                <td class="px-4 py-3"><div class="font-semibold {{ $discountApplied ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-600 dark:text-slate-300' }}">{{ $discountLabel }}</div>@if ($discountApplied)<div class="mt-1 text-xs capitalize text-slate-500">{{ $itemKit->discount_type }}</div>@elseif ($itemKit->price_option === 'components')<div class="mt-1 text-xs text-slate-500">Component pricing</div>@endif</td>
                                <td class="px-4 py-3"><div class="font-medium text-slate-700 dark:text-slate-300">{{ $itemKit->print_option === 'kit_only' ? 'Kit only' : 'All lines' }}</div><div class="mt-1 text-xs text-slate-500">{{ $itemKit->print_option === 'kit_only' ? 'Components hidden' : 'Components shown' }}</div></td>
                                <td class="px-3 py-3"><div class="flex justify-end gap-1">@can('update', $itemKit)<a href="{{ route('item-kits.edit', $itemKit) }}" title="Edit kit" aria-label="Edit {{ $itemKit->name }}" class="grid h-8 w-8 place-items-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-emerald-700 dark:hover:bg-slate-800"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M15 5l4 4L10 18l-5 1 1-5 9-9z"/></svg></a>@endcan @can('delete', $itemKit)<button wire:click="delete({{ $itemKit->id }})" wire:confirm="Delete this item kit?" title="Delete kit" aria-label="Delete {{ $itemKit->name }}" class="grid h-8 w-8 place-items-center rounded-md text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/40"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 7h12m-10 0l1 13h6l1-13M9 7V4h6v3"/></svg></button>@endcan</div></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-14 text-center"><h3 class="text-sm font-semibold text-slate-900 dark:text-white">No item kits match this view</h3><p class="mt-1 text-sm text-slate-500">Adjust the filters or create a new kit.</p>@if ($hasFilters)<button wire:click="clearFilters" class="mt-3 text-sm font-semibold text-emerald-700">Clear filters</button>@endif</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-slate-200 dark:divide-slate-800 lg:hidden">
                @forelse ($itemKits as $itemKit)
                    @php
                        $discountApplied = $itemKit->price_option !== 'components' && bccomp((string) $itemKit->discount_value, '0', 4) > 0;
                        $discountLabel = $discountApplied ? ($itemKit->discount_type === 'percent' ? rtrim(rtrim((string) $itemKit->discount_value, '0'), '.').'%' : (string) \App\Support\Money\Money::of($itemKit->discount_value)) : 'None';
                    @endphp
                    <article wire:key="kit-card-{{ $itemKit->id }}" class="p-4">
                        <div class="flex items-start justify-between gap-3"><div class="min-w-0"><h3 class="truncate text-sm font-semibold text-slate-950 dark:text-white">{{ $itemKit->name }}</h3><p class="mt-0.5 truncate font-mono text-xs text-slate-500">{{ $itemKit->kit_number }}</p></div><span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold {{ $pricingStyles[$itemKit->price_option] ?? 'bg-slate-100 text-slate-600' }}">{{ $pricingLabels[$itemKit->price_option] ?? ucfirst($itemKit->price_option) }}</span></div>
                        <dl class="mt-3 grid grid-cols-3 gap-3 text-xs"><div><dt class="text-slate-500">Components</dt><dd class="mt-0.5 font-semibold tabular-nums">{{ number_format($itemKit->items_count) }}</dd></div><div><dt class="text-slate-500">Discount</dt><dd class="mt-0.5 font-semibold {{ $discountApplied ? 'text-emerald-700' : '' }}">{{ $discountLabel }}</dd></div><div><dt class="text-slate-500">Receipt</dt><dd class="mt-0.5 font-medium">{{ $itemKit->print_option === 'kit_only' ? 'Kit only' : 'All lines' }}</dd></div></dl>
                        @if ($itemKit->description)<p class="mt-3 line-clamp-2 text-xs text-slate-500">{{ $itemKit->description }}</p>@endif
                        <div class="mt-3 flex justify-end gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">@can('update', $itemKit)<a href="{{ route('item-kits.edit', $itemKit) }}" class="inline-flex h-9 items-center rounded-md border border-slate-300 px-3 text-xs font-semibold dark:border-slate-700">Edit</a>@endcan @can('delete', $itemKit)<button wire:click="delete({{ $itemKit->id }})" wire:confirm="Delete this item kit?" aria-label="Delete {{ $itemKit->name }}" class="grid h-9 w-9 place-items-center rounded-md text-red-600"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 7h12m-10 0l1 13h6l1-13M9 7V4h6v3"/></svg></button>@endcan</div>
                    </article>
                @empty
                    <div class="px-5 py-14 text-center"><h3 class="text-sm font-semibold">No item kits match this view</h3><p class="mt-1 text-sm text-slate-500">Adjust the filters or create a new kit.</p>@if ($hasFilters)<button wire:click="clearFilters" class="mt-3 text-sm font-semibold text-emerald-700">Clear filters</button>@endif</div>
                @endforelse
            </div>
        </section>

        @if ($itemKits->hasPages())<div>{{ $itemKits->links() }}</div>@endif
    </div>
</div>
