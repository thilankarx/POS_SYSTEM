<div>
    @section('title', 'Attributes')
    @php
        $hasFilters = $search !== '' || $type !== '' || $level !== '' || $visibility !== '' || $usage !== '' || $sort !== 'name';
        $typeLabels = ['text' => 'Text', 'dropdown' => 'Dropdown', 'decimal' => 'Number', 'date' => 'Date', 'checkbox' => 'Yes / no', 'group' => 'Group'];
        $typeStyles = ['text' => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300', 'dropdown' => 'bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300', 'decimal' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300', 'date' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300', 'checkbox' => 'bg-cyan-50 text-cyan-700 dark:bg-cyan-500/10 dark:text-cyan-300', 'group' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'];
    @endphp

    <div class="space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><h2 class="text-2xl font-bold text-slate-950 dark:text-white">Attributes</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Manage structured item details, reusable values, and catalog visibility.</p></div>
            @can('create', \App\Domain\Catalog\Models\AttributeDefinition::class)
                <a href="{{ route('attribute-definitions.create') }}" class="inline-flex h-10 items-center gap-2 self-start rounded-md bg-emerald-600 px-3.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 sm:self-auto"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>New attribute</a>
            @endcan
        </header>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <dl class="grid grid-cols-2 divide-x divide-y divide-slate-200 dark:divide-slate-800 lg:grid-cols-4 lg:divide-y-0">
                @foreach ([['Matching attributes', $summary['total'], 'Current filters'], ['Groups', $summary['groups'], 'Organizational'], ['Search fields', $summary['searchable'], 'Visible in search'], ['Assignments', $summary['assignments'], 'Linked to items']] as [$label, $value, $note])
                    <div class="p-4 sm:p-5"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">{{ $label }}</dt><dd class="mt-2 text-2xl font-bold tabular-nums {{ $label === 'Assignments' ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-950 dark:text-white' }}">{{ number_format($value) }}</dd><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $note }}</p></div>
                @endforeach
            </dl>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-[minmax(15rem,1.3fr)_10rem_11rem_12rem_11rem_11rem_auto] 2xl:items-end">
                <div><label for="attribute-search" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Search</label><div class="relative"><svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M21 21l-4.35-4.35m2.35-5.65a8 8 0 11-16 0 8 8 0 0116 0z"/></svg><input wire:model.live.debounce.300ms="search" id="attribute-search" type="search" placeholder="Name, unit, or parent" class="h-10 w-full rounded-md border border-slate-300 bg-white pl-9 pr-3 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950"></div></div>
                <div><label for="attribute-type" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Data type</label><select wire:model.live="type" id="attribute-type" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">All types</option>@foreach ($typeLabels as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                <div><label for="attribute-level" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Hierarchy</label><select wire:model.live="level" id="attribute-level" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">All levels</option><option value="top">Top level</option><option value="child">Nested</option></select></div>
                <div><label for="attribute-visibility" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Visibility</label><select wire:model.live="visibility" id="attribute-visibility" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">Any visibility</option><option value="search">Shown in search</option><option value="receipt">Shown on receipt</option><option value="hidden">Neither</option></select></div>
                <div><label for="attribute-usage" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Usage</label><select wire:model.live="usage" id="attribute-usage" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="">Any usage</option><option value="used">Assigned</option><option value="unused">Unassigned</option></select></div>
                <div><label for="attribute-sort" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Sort</label><select wire:model.live="sort" id="attribute-sort" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm dark:border-slate-700 dark:bg-slate-950"><option value="name">Name</option><option value="type">Data type</option><option value="usage">Most assigned</option><option value="newest">Newest</option></select></div>
                @if ($hasFilters)<button type="button" wire:click="clearFilters" class="h-10 rounded-md px-3 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">Clear</button>@endif
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900" wire:loading.class="opacity-60" wire:target="search,type,level,visibility,usage,sort,clearFilters">
            <div class="hidden overflow-x-auto lg:block">
                <table class="w-full min-w-[960px] text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-950/50 dark:text-slate-400"><tr><th class="px-4 py-3 font-semibold">Attribute</th><th class="px-4 py-3 font-semibold">Data type</th><th class="px-4 py-3 font-semibold">Hierarchy</th><th class="px-4 py-3 text-right font-semibold">Values</th><th class="px-4 py-3 text-right font-semibold">Assignments</th><th class="px-4 py-3 font-semibold">Visibility</th><th class="w-24 px-3 py-3"></th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($attributeDefinitions as $definition)
                            <tr wire:key="attribute-{{ $definition->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                <td class="px-4 py-3"><div class="font-semibold text-slate-950 dark:text-white">{{ $definition->name }}</div><div class="mt-1 text-xs text-slate-500">{{ $definition->unit ? 'Unit: '.$definition->unit : ($definition->type === 'group' ? $definition->children_count.' '.str('child')->plural($definition->children_count) : 'No unit') }}</div></td>
                                <td class="px-4 py-3"><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $typeStyles[$definition->type] ?? 'bg-slate-100 text-slate-600' }}">{{ $typeLabels[$definition->type] ?? ucfirst($definition->type) }}</span></td>
                                <td class="px-4 py-3">@if ($definition->parent)<div class="font-medium text-slate-700 dark:text-slate-300">{{ $definition->parent->name }}</div><div class="mt-1 text-xs text-slate-500">Nested attribute</div>@else<div class="font-medium text-slate-700 dark:text-slate-300">Top level</div><div class="mt-1 text-xs text-slate-500">{{ $definition->type === 'group' ? 'Attribute group' : 'Standalone' }}</div>@endif</td>
                                <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ number_format($definition->values_count) }}</td>
                                <td class="px-4 py-3 text-right"><span class="font-semibold tabular-nums {{ $definition->assignments_count > 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-500' }}">{{ number_format($definition->assignments_count) }}</span></td>
                                <td class="px-4 py-3"><div class="flex flex-wrap gap-1.5">@if ($definition->show_in_search)<span class="rounded bg-sky-50 px-2 py-0.5 text-xs font-medium text-sky-700 dark:bg-sky-500/10 dark:text-sky-300">Search</span>@endif @if ($definition->show_in_receipt)<span class="rounded bg-violet-50 px-2 py-0.5 text-xs font-medium text-violet-700 dark:bg-violet-500/10 dark:text-violet-300">Receipt</span>@endif @if (! $definition->show_in_search && ! $definition->show_in_receipt)<span class="text-xs text-slate-500">Internal only</span>@endif</div></td>
                                <td class="px-3 py-3"><div class="flex justify-end gap-1">@can('update', $definition)<a href="{{ route('attribute-definitions.edit', $definition) }}" title="Edit attribute" aria-label="Edit {{ $definition->name }}" class="grid h-8 w-8 place-items-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-emerald-700 dark:hover:bg-slate-800"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M15 5l4 4L10 18l-5 1 1-5 9-9z"/></svg></a>@endcan @can('delete', $definition)<button wire:click="delete({{ $definition->id }})" wire:confirm="Delete this attribute? Its stored values and item assignments will also be removed." title="Delete attribute" aria-label="Delete {{ $definition->name }}" class="grid h-8 w-8 place-items-center rounded-md text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/40"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 7h12m-10 0l1 13h6l1-13M9 7V4h6v3"/></svg></button>@endcan</div></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-5 py-14 text-center"><h3 class="text-sm font-semibold">No attributes match this view</h3><p class="mt-1 text-sm text-slate-500">Adjust the filters or create a new attribute.</p>@if ($hasFilters)<button wire:click="clearFilters" class="mt-3 text-sm font-semibold text-emerald-700">Clear filters</button>@endif</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="divide-y divide-slate-200 dark:divide-slate-800 lg:hidden">
                @forelse ($attributeDefinitions as $definition)
                    <article wire:key="attribute-card-{{ $definition->id }}" class="p-4">
                        <div class="flex items-start justify-between gap-3"><div class="min-w-0"><h3 class="truncate text-sm font-semibold">{{ $definition->name }}</h3><p class="mt-0.5 truncate text-xs text-slate-500">{{ $definition->parent?->name ?? 'Top level' }}{{ $definition->unit ? ' · '.$definition->unit : '' }}</p></div><span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold {{ $typeStyles[$definition->type] ?? 'bg-slate-100 text-slate-600' }}">{{ $typeLabels[$definition->type] ?? ucfirst($definition->type) }}</span></div>
                        <dl class="mt-3 grid grid-cols-3 gap-3 text-xs"><div><dt class="text-slate-500">Values</dt><dd class="mt-0.5 font-semibold tabular-nums">{{ number_format($definition->values_count) }}</dd></div><div><dt class="text-slate-500">Assignments</dt><dd class="mt-0.5 font-semibold tabular-nums {{ $definition->assignments_count > 0 ? 'text-emerald-700' : '' }}">{{ number_format($definition->assignments_count) }}</dd></div><div><dt class="text-slate-500">Visible in</dt><dd class="mt-0.5 truncate font-medium">{{ collect([$definition->show_in_search ? 'Search' : null, $definition->show_in_receipt ? 'Receipt' : null])->filter()->join(', ') ?: 'Internal' }}</dd></div></dl>
                        <div class="mt-3 flex justify-end gap-2 border-t border-slate-100 pt-3 dark:border-slate-800">@can('update', $definition)<a href="{{ route('attribute-definitions.edit', $definition) }}" class="inline-flex h-9 items-center rounded-md border border-slate-300 px-3 text-xs font-semibold dark:border-slate-700">Edit</a>@endcan @can('delete', $definition)<button wire:click="delete({{ $definition->id }})" wire:confirm="Delete this attribute? Its stored values and item assignments will also be removed." aria-label="Delete {{ $definition->name }}" class="grid h-9 w-9 place-items-center rounded-md text-red-600"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 7h12m-10 0l1 13h6l1-13M9 7V4h6v3"/></svg></button>@endcan</div>
                    </article>
                @empty
                    <div class="px-5 py-14 text-center"><h3 class="text-sm font-semibold">No attributes match this view</h3><p class="mt-1 text-sm text-slate-500">Adjust the filters or create a new attribute.</p>@if ($hasFilters)<button wire:click="clearFilters" class="mt-3 text-sm font-semibold text-emerald-700">Clear filters</button>@endif</div>
                @endforelse
            </div>
        </section>
        @if ($attributeDefinitions->hasPages())<div>{{ $attributeDefinitions->links() }}</div>@endif
    </div>
</div>
