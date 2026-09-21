<div>
    @section('title', $attributeDefinition ? 'Edit attribute' : 'New attribute')
    @php
        $typeLabels = ['text' => 'Text', 'dropdown' => 'Dropdown', 'decimal' => 'Number', 'date' => 'Date', 'checkbox' => 'Yes / no', 'group' => 'Group'];
        $typeNotes = ['text' => 'Free-form text value', 'dropdown' => 'Reusable predefined values', 'decimal' => 'Numeric value with optional unit', 'date' => 'Calendar date', 'checkbox' => 'Boolean yes or no', 'group' => 'Organizes related attributes'];
        $isGroup = $type === \App\Domain\Catalog\Models\AttributeDefinition::TYPE_GROUP;
    @endphp

    <div class="max-w-6xl space-y-6">
        <header class="flex flex-col gap-4 border-b border-slate-200 pb-4 dark:border-slate-800 sm:flex-row sm:items-end sm:justify-between">
            <div><p class="text-xs font-semibold uppercase text-emerald-700 dark:text-emerald-400">Catalog</p><h2 class="mt-1 text-2xl font-bold text-slate-950 dark:text-white">{{ $attributeDefinition ? 'Edit attribute' : 'New attribute' }}</h2><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Define structured information available on catalog items.</p></div>
            <a href="{{ route('attribute-definitions.index') }}" class="inline-flex h-10 items-center gap-2 self-start rounded-md border border-slate-300 bg-white px-3.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 sm:self-auto"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M15 19l-7-7 7-7"/></svg>Back to attributes</a>
        </header>

        <form wire:submit="save" class="space-y-4">
            <div class="grid gap-4 lg:grid-cols-3 lg:items-start">
                <div class="space-y-4 lg:col-span-2">
                    <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
                        <div class="mb-5 flex items-center gap-3"><span class="grid h-9 w-9 place-items-center rounded-md bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M7 7h.01M3 11l8-8h8v8l-8 8-8-8z"/></svg></span><div><h3 class="text-sm font-semibold">Attribute details</h3><p class="text-xs text-slate-500">Name, data type, and value format</p></div></div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2"><label for="name" class="mb-1.5 block text-sm font-semibold">Attribute name <span class="text-red-600">*</span></label><input wire:model.live.debounce.300ms="name" id="name" type="text" maxlength="255" autofocus autocomplete="off" placeholder="e.g. Weight" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">@error('name')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                            <div><label for="type" class="mb-1.5 block text-sm font-semibold">Data type <span class="text-red-600">*</span></label><select wire:model.live="type" id="type" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">@foreach ($typeLabels as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select><p class="mt-1.5 text-xs text-slate-500">{{ $typeNotes[$type] ?? '' }}</p>@error('type')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                            <div>
                                <label for="unit" class="mb-1.5 block text-sm font-semibold">Unit</label>
                                <input wire:model.live.debounce.300ms="unit" id="unit" type="text" maxlength="16" placeholder="{{ $isGroup ? 'Not applicable to groups' : 'e.g. kg, cm, ml' }}" @disabled($isGroup) class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm shadow-sm disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400 dark:border-slate-700 dark:bg-slate-950 dark:disabled:bg-slate-800">
                                @error('unit')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </section>

                    <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
                        <div class="mb-5 flex items-center gap-3"><span class="grid h-9 w-9 place-items-center rounded-md bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 4v9a3 3 0 003 3h9m-3-3l3 3-3 3"/></svg></span><div><h3 class="text-sm font-semibold">Hierarchy</h3><p class="text-xs text-slate-500">Place this definition inside an attribute group</p></div></div>
                        <label for="parent_id" class="mb-1.5 block text-sm font-semibold">Parent attribute</label>
                        <select wire:model.live="parent_id" id="parent_id" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">
                            <option value="">No parent (top level)</option>
                            @foreach ($attributeDefinitions as $option)
                                <option value="{{ $option->id }}">{{ $option->parent ? $option->parent->name.' / ' : '' }}{{ $option->name }} · {{ $typeLabels[$option->type] ?? ucfirst($option->type) }}{{ $option->children_count ? ' · '.$option->children_count.' children' : '' }}</option>
                            @endforeach
                        </select>
                        @error('parent_id')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                    </section>

                    @if (! $isGroup)
                        <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
                            <div class="mb-5 flex items-center gap-3"><span class="grid h-9 w-9 place-items-center rounded-md bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M3 12s3-6 9-6 9 6 9 6-3 6-9 6-9-6-9-6zm9-2a2 2 0 100 4 2 2 0 000-4z"/></svg></span><div><h3 class="text-sm font-semibold">Visibility</h3><p class="text-xs text-slate-500">Choose where populated values appear</p></div></div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="flex min-h-16 cursor-pointer items-start gap-3 rounded-md border border-slate-200 p-3 hover:border-emerald-400 dark:border-slate-700"><input wire:model.live="show_in_search" type="checkbox" class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"><span><span class="block text-sm font-semibold">Show in search</span><span class="mt-0.5 block text-xs text-slate-500">Available as catalog search information</span></span></label>
                                <label class="flex min-h-16 cursor-pointer items-start gap-3 rounded-md border border-slate-200 p-3 hover:border-emerald-400 dark:border-slate-700"><input wire:model.live="show_in_receipt" type="checkbox" class="mt-0.5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"><span><span class="block text-sm font-semibold">Show on receipt</span><span class="mt-0.5 block text-xs text-slate-500">Print populated values with sold items</span></span></label>
                            </div>
                        </section>
                    @endif
                </div>

                <aside class="space-y-4 lg:sticky lg:top-20">
                    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-800"><h3 class="text-sm font-semibold">Attribute preview</h3></div>
                        <div class="p-4"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="truncate font-semibold">{{ $name !== '' ? $name : 'Untitled attribute' }}</p><p class="mt-1 truncate text-xs text-slate-500">{{ $selectedParent?->name ?? 'Top level' }}</p></div><span class="shrink-0 rounded-full bg-sky-50 px-2.5 py-1 text-xs font-semibold text-sky-700 dark:bg-sky-500/10 dark:text-sky-300">{{ $typeLabels[$type] ?? ucfirst($type) }}</span></div></div>
                        <dl class="divide-y divide-slate-100 border-t border-slate-100 text-sm dark:divide-slate-800 dark:border-slate-800">
                            <div class="flex justify-between gap-4 px-4 py-3"><dt class="text-slate-500">Unit</dt><dd class="font-medium">{{ $isGroup ? 'Not applicable' : ($unit !== '' ? $unit : 'None') }}</dd></div>
                            <div class="flex justify-between gap-4 px-4 py-3"><dt class="text-slate-500">Search</dt><dd class="font-medium">{{ ! $isGroup && $show_in_search ? 'Visible' : 'Hidden' }}</dd></div>
                            <div class="flex justify-between gap-4 px-4 py-3"><dt class="text-slate-500">Receipt</dt><dd class="font-medium">{{ ! $isGroup && $show_in_receipt ? 'Visible' : 'Hidden' }}</dd></div>
                        </dl>
                    </section>
                    @if ($isGroup)<div class="rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm text-sky-800 dark:border-sky-900 dark:bg-sky-950/30 dark:text-sky-200"><div class="flex gap-3"><svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 9h.01M11 12h1v4h1m8-4a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><p>Groups organize child attributes and do not store item values.</p></div></div>@endif
                </aside>
            </div>

            <section class="flex flex-col-reverse gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:flex-row sm:items-center sm:justify-end"><a href="{{ route('attribute-definitions.index') }}" class="inline-flex h-10 items-center justify-center px-3 text-sm font-semibold text-slate-600 dark:text-slate-300">Cancel</a><button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex h-10 min-w-36 items-center justify-center rounded-md bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:opacity-60"><span wire:loading.remove wire:target="save">{{ $attributeDefinition ? 'Save changes' : 'Create attribute' }}</span><span wire:loading wire:target="save">Saving&hellip;</span></button></section>
        </form>
    </div>
</div>
