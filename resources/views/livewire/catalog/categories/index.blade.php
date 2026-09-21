<div>
    @section('title', 'Categories')
    @php $hasFilters = $search !== '' || $level !== '' || $usage !== '' || $sort !== 'name'; @endphp

    <div class="space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-950 dark:text-white">Categories</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Organize items and maintain category-based SKU prefixes.</p>
            </div>
            @can('create', \App\Domain\Catalog\Models\Category::class)
                <a href="{{ route('categories.create') }}" class="inline-flex h-10 items-center gap-2 self-start rounded-md bg-emerald-600 px-3.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 sm:self-auto">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                    New category
                </a>
            @endcan
        </header>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <dl class="grid grid-cols-2 divide-x divide-y divide-slate-200 dark:divide-slate-800 lg:grid-cols-4 lg:divide-y-0">
                @foreach ([['Matching categories', $summary['total'], 'Current filters'], ['Top level', $summary['topLevel'], 'Primary groups'], ['Subcategories', $summary['subcategories'], 'Nested groups'], ['Assigned items', $summary['items'], 'Across this view']] as [$label, $value, $note])
                    <div class="p-4 sm:p-5">
                        <dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">{{ $label }}</dt>
                        <dd class="mt-2 text-2xl font-bold tabular-nums {{ $label === 'Assigned items' ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-950 dark:text-white' }}">{{ number_format($value) }}</dd>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $note }}</p>
                    </div>
                @endforeach
            </dl>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(16rem,1fr)_12rem_12rem_12rem_auto] lg:items-end">
                <div class="min-w-0">
                    <label for="category-search" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Search</label>
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M21 21l-4.35-4.35m2.35-5.65a8 8 0 11-16 0 8 8 0 0116 0z"/></svg>
                        <input wire:model.live.debounce.300ms="search" id="category-search" type="search" placeholder="Name, code, slug, or parent" class="h-10 w-full rounded-md border border-slate-300 bg-white pl-9 pr-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-950">
                    </div>
                </div>
                <div><label for="category-level" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Hierarchy</label><select wire:model.live="level" id="category-level" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950"><option value="">All levels</option><option value="top">Top level</option><option value="child">Subcategories</option></select></div>
                <div><label for="category-usage" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Usage</label><select wire:model.live="usage" id="category-usage" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950"><option value="">All categories</option><option value="used">Has items</option><option value="empty">No items</option></select></div>
                <div><label for="category-sort" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Sort</label><select wire:model.live="sort" id="category-sort" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950"><option value="name">Name</option><option value="code">Code</option><option value="items">Most items</option><option value="newest">Newest</option></select></div>
                @if ($hasFilters)<button type="button" wire:click="clearFilters" class="h-10 rounded-md px-3 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">Clear</button>@endif
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900" wire:loading.class="opacity-60" wire:target="search,level,usage,sort,clearFilters">
            <div class="hidden overflow-x-auto md:block">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-950/50 dark:text-slate-400">
                        <tr><th class="px-4 py-3 font-semibold">Category</th><th class="px-4 py-3 font-semibold">Code</th><th class="px-4 py-3 font-semibold">Hierarchy</th><th class="px-4 py-3 text-right font-semibold">Items</th><th class="px-4 py-3 text-right font-semibold">Children</th><th class="w-24 px-3 py-3"><span class="sr-only">Actions</span></th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($categories as $category)
                            <tr wire:key="category-{{ $category->id }}" class="transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="px-4 py-3"><div class="font-semibold text-slate-950 dark:text-white">{{ $category->name }}</div><div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $category->slug }}</div></td>
                                <td class="px-4 py-3"><span class="rounded bg-slate-100 px-2 py-1 font-mono text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $category->code ?: '—' }}</span></td>
                                <td class="px-4 py-3">@if ($category->parent)<div class="flex items-center gap-2 text-slate-700 dark:text-slate-300"><svg class="h-4 w-4 shrink-0 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 4v9a3 3 0 003 3h9m-3-3l3 3-3 3"/></svg>{{ $category->parent->name }}</div><div class="mt-1 text-xs text-slate-500">Subcategory</div>@else<div class="font-medium text-slate-700 dark:text-slate-300">Top level</div><div class="mt-1 text-xs text-slate-500">Primary category</div>@endif</td>
                                <td class="px-4 py-3 text-right"><span class="font-semibold tabular-nums text-slate-950 dark:text-white">{{ number_format($category->items_count) }}</span></td>
                                <td class="px-4 py-3 text-right"><span class="tabular-nums text-slate-700 dark:text-slate-300">{{ number_format($category->children_count) }}</span></td>
                                <td class="px-3 py-3"><div class="flex justify-end gap-1">@can('update', $category)<a href="{{ route('categories.edit', $category) }}" title="Edit category" aria-label="Edit {{ $category->name }}" class="grid h-8 w-8 place-items-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-emerald-700 dark:hover:bg-slate-800"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M15 5l4 4L10 18l-5 1 1-5 9-9z"/></svg></a>@endcan @can('delete', $category)<button wire:click="delete({{ $category->id }})" wire:confirm="Delete this category? Its items and subcategories will become uncategorized." title="Delete category" aria-label="Delete {{ $category->name }}" class="grid h-8 w-8 place-items-center rounded-md text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/40"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 7h12m-10 0l1 13h6l1-13M9 7V4h6v3"/></svg></button>@endcan</div></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-14 text-center"><h3 class="text-sm font-semibold text-slate-900 dark:text-white">No categories match this view</h3><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Adjust the filters or create a new category.</p>@if ($hasFilters)<button wire:click="clearFilters" class="mt-3 text-sm font-semibold text-emerald-700 dark:text-emerald-400">Clear filters</button>@endif</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-slate-200 dark:divide-slate-800 md:hidden">
                @forelse ($categories as $category)
                    <article wire:key="category-card-{{ $category->id }}" class="p-4">
                        <div class="flex items-start justify-between gap-3"><div class="min-w-0"><h3 class="truncate text-sm font-semibold text-slate-950 dark:text-white">{{ $category->name }}</h3><p class="mt-0.5 truncate text-xs text-slate-500">{{ $category->slug }}</p></div><span class="shrink-0 rounded bg-slate-100 px-2 py-1 font-mono text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $category->code ?: 'No code' }}</span></div>
                        <dl class="mt-3 grid grid-cols-3 gap-3 text-xs"><div><dt class="text-slate-500">Level</dt><dd class="mt-0.5 truncate font-medium">{{ $category->parent?->name ?? 'Top level' }}</dd></div><div><dt class="text-slate-500">Items</dt><dd class="mt-0.5 font-semibold tabular-nums">{{ number_format($category->items_count) }}</dd></div><div><dt class="text-slate-500">Children</dt><dd class="mt-0.5 font-semibold tabular-nums">{{ number_format($category->children_count) }}</dd></div></dl>
                        <div class="mt-3 flex justify-end gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">@can('update', $category)<a href="{{ route('categories.edit', $category) }}" class="inline-flex h-9 items-center rounded-md border border-slate-300 px-3 text-xs font-semibold dark:border-slate-700">Edit</a>@endcan @can('delete', $category)<button wire:click="delete({{ $category->id }})" wire:confirm="Delete this category? Its items and subcategories will become uncategorized." aria-label="Delete {{ $category->name }}" class="grid h-9 w-9 place-items-center rounded-md text-red-600"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 7h12m-10 0l1 13h6l1-13M9 7V4h6v3"/></svg></button>@endcan</div>
                    </article>
                @empty
                    <div class="px-5 py-14 text-center"><h3 class="text-sm font-semibold">No categories match this view</h3><p class="mt-1 text-sm text-slate-500">Adjust the filters or create a new category.</p>@if ($hasFilters)<button wire:click="clearFilters" class="mt-3 text-sm font-semibold text-emerald-700">Clear filters</button>@endif</div>
                @endforelse
            </div>
        </section>

        @if ($categories->hasPages())<div>{{ $categories->links() }}</div>@endif
    </div>
</div>
