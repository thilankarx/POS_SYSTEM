<div>
    @section('title', $category ? 'Edit category' : 'New category')

    <div class="max-w-6xl space-y-6">
        <header class="flex flex-col gap-4 border-b border-slate-200 pb-4 dark:border-slate-800 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase text-emerald-700 dark:text-emerald-400">Catalog</p>
                <h2 class="mt-1 text-2xl font-bold text-slate-950 dark:text-white">{{ $category ? 'Edit category' : 'New category' }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $category ? 'Update its hierarchy and catalog identifiers.' : 'Create an item group with a consistent SKU prefix.' }}</p>
            </div>
            <a href="{{ route('categories.index') }}" class="inline-flex h-10 items-center gap-2 self-start rounded-md border border-slate-300 bg-white px-3.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 sm:self-auto">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                Back to categories
            </a>
        </header>

        <form wire:submit="save" class="space-y-4">
            <div class="grid gap-4 lg:grid-cols-3 lg:items-start">
                <div class="space-y-4 lg:col-span-2">
                    <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
                        <div class="mb-5 flex items-center gap-3">
                            <span class="grid h-9 w-9 place-items-center rounded-md bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h6l2 2h8v10H4V7zm0 0V5h6l2 2"/></svg>
                            </span>
                            <div><h3 class="text-sm font-semibold text-slate-950 dark:text-white">Category details</h3><p class="text-xs text-slate-500 dark:text-slate-400">Name and catalog hierarchy</p></div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label for="name" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200">Category name <span class="text-red-600">*</span></label>
                                <input wire:model.live.debounce.300ms="name" id="name" type="text" maxlength="255" autofocus autocomplete="off" placeholder="e.g. Plumbing fittings" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-950">
                                @error('name')<p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                            </div>

                            <div class="sm:col-span-2">
                                <label for="parent_id" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200">Parent category</label>
                                <select wire:model.live="parent_id" id="parent_id" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-950">
                                    <option value="">No parent (top level)</option>
                                    @foreach ($categories as $option)
                                        <option value="{{ $option->id }}">{{ $option->parent ? $option->parent->name.' / ' : '' }}{{ $option->name }} · {{ $option->items_count }} {{ str('item')->plural($option->items_count) }}</option>
                                    @endforeach
                                </select>
                                @error('parent_id')<p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </section>

                    <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
                        <div class="mb-5 flex items-center gap-3">
                            <span class="grid h-9 w-9 place-items-center rounded-md bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h.01M3 11l8-8h8v8l-8 8-8-8z"/></svg>
                            </span>
                            <div><h3 class="text-sm font-semibold text-slate-950 dark:text-white">Identifiers</h3><p class="text-xs text-slate-500 dark:text-slate-400">URL slug and item SKU prefix</p></div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="slug" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200">Slug</label>
                                <input wire:model.live.debounce.300ms="slug" id="slug" type="text" maxlength="255" autocomplete="off" placeholder="{{ $slugPreview ?: 'auto-generated' }}" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 font-mono text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-950">
                                <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">Saved as <span class="font-mono text-slate-700 dark:text-slate-300">{{ $slugPreview ?: 'after entering a name' }}</span></p>
                                @error('slug')<p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="code" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200">SKU prefix</label>
                                <input wire:model.live.debounce.300ms="code" id="code" type="text" maxlength="10" autocomplete="off" placeholder="{{ $codePreview }}" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 font-mono text-sm uppercase shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-950">
                                <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">Up to 10 characters; stored as uppercase.</p>
                                @error('code')<p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                            </div>
                        </div>
                    </section>
                </div>

                <aside class="space-y-4 lg:sticky lg:top-20">
                    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-800"><h3 class="text-sm font-semibold text-slate-950 dark:text-white">Category preview</h3></div>
                        <div class="p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0"><p class="truncate font-semibold text-slate-950 dark:text-white">{{ $name !== '' ? $name : 'Untitled category' }}</p><p class="mt-1 truncate font-mono text-xs text-slate-500">{{ $slugPreview ?: 'category-slug' }}</p></div>
                                <span class="shrink-0 rounded bg-slate-100 px-2 py-1 font-mono text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $codePreview }}</span>
                            </div>
                        </div>
                        <dl class="divide-y divide-slate-100 border-t border-slate-100 text-sm dark:divide-slate-800 dark:border-slate-800">
                            <div class="flex items-start justify-between gap-4 px-4 py-3"><dt class="text-slate-500 dark:text-slate-400">Level</dt><dd class="max-w-44 text-right font-medium">{{ $selectedParent ? 'Subcategory' : 'Top level' }}</dd></div>
                            <div class="flex items-start justify-between gap-4 px-4 py-3"><dt class="text-slate-500 dark:text-slate-400">Parent</dt><dd class="max-w-44 truncate text-right font-medium">{{ $selectedParent?->name ?? 'None' }}</dd></div>
                            <div class="flex items-start justify-between gap-4 px-4 py-3"><dt class="text-slate-500 dark:text-slate-400">Example SKU</dt><dd class="font-mono font-semibold text-emerald-700 dark:text-emerald-400">{{ $codePreview }}-000001</dd></div>
                        </dl>
                    </section>

                    @if ($category)
                        <section class="rounded-lg border border-slate-200 bg-white p-4 text-sm shadow-sm dark:border-slate-800 dark:bg-slate-900">
                            <h3 class="font-semibold text-slate-950 dark:text-white">Record details</h3>
                            <dl class="mt-3 space-y-2"><div class="flex justify-between gap-3"><dt class="text-slate-500">Created</dt><dd>{{ $category->created_at->format('d M Y') }}</dd></div><div class="flex justify-between gap-3"><dt class="text-slate-500">Updated</dt><dd>{{ $category->updated_at->format('d M Y, g:i A') }}</dd></div></dl>
                        </section>
                    @endif
                </aside>
            </div>

            <section class="flex flex-col-reverse gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:flex-row sm:items-center sm:justify-end">
                <a href="{{ route('categories.index') }}" class="inline-flex h-10 items-center justify-center px-3 text-sm font-semibold text-slate-600 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white">Cancel</a>
                <button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex h-10 min-w-36 items-center justify-center rounded-md bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60">
                    <span wire:loading.remove wire:target="save">{{ $category ? 'Save changes' : 'Create category' }}</span>
                    <span wire:loading wire:target="save">Saving&hellip;</span>
                </button>
            </section>
        </form>
    </div>
</div>
