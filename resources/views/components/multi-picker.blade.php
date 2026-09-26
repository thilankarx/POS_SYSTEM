@props(['options' => [], 'placeholder' => 'Search…', 'noun' => 'options'])

{{--
    Searchable checkbox list for picking several ids. Bind it with
    wire:model on the component tag; x-modelable hands `selected` (an array
    of string ids) to Livewire. Give the tag a wire:key that changes with the
    option set, since x-data only reads `options` once.
--}}
<div
    wire:ignore
    x-data="{
        options: @js(collect($options)->map(fn ($label, $value) => ['value' => (string) $value, 'label' => (string) $label])->values()),
        selected: [],
        query: '',
        get filtered() {
            const needles = this.query.toLowerCase().split(/\s+/).filter(Boolean);
            return needles.length
                ? this.options.filter((option) => needles.every((needle) => option.label.toLowerCase().includes(needle)))
                : this.options;
        },
        get selectedOptions() {
            const ids = this.selected.map(String);
            return this.options.filter((option) => ids.includes(option.value));
        },
        isSelected(value) {
            return this.selected.map(String).includes(value);
        },
        toggle(value) {
            this.selected = this.isSelected(value)
                ? this.selected.filter((id) => String(id) !== value)
                : [...this.selected.map(String), value];
        },
        selectFiltered() {
            const ids = this.selected.map(String);
            this.selected = [...ids, ...this.filtered.map((option) => option.value).filter((value) => ! ids.includes(value))];
        },
        clear() {
            this.selected = [];
        },
    }"
    x-modelable="selected"
    {{ $attributes->merge(['class' => 'overflow-hidden rounded-md border border-slate-300 bg-white shadow-sm focus-within:border-emerald-500 focus-within:ring-2 focus-within:ring-emerald-500/20 dark:border-slate-700 dark:bg-slate-950']) }}
>
    <div class="flex min-h-10 flex-wrap items-center gap-1.5 border-b border-slate-200 px-2 py-1.5 dark:border-slate-800">
        <template x-for="option in selectedOptions" :key="option.value">
            <span class="inline-flex max-w-full items-center gap-1 rounded-full bg-emerald-50 py-0.5 pl-2.5 pr-1 text-xs font-medium text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300">
                <span class="truncate" x-text="option.label"></span>
                <button type="button" @click="toggle(option.value)" class="grid h-4 w-4 shrink-0 place-items-center rounded-full hover:bg-emerald-200 dark:hover:bg-emerald-500/30" :aria-label="`Remove ${option.label}`">
                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18" /></svg>
                </button>
            </span>
        </template>
        <span x-show="! selected.length" class="px-1 text-sm text-slate-400">Nothing selected yet</span>
    </div>

    <div class="relative border-b border-slate-200 dark:border-slate-800">
        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" /></svg>
        <input
            x-model="query"
            type="search"
            placeholder="{{ $placeholder }}"
            @keydown.enter.prevent="filtered.length === 1 && toggle(filtered[0].value)"
            class="h-9 w-full border-0 bg-transparent pl-9 pr-3 text-sm outline-none placeholder:text-slate-400 focus:ring-0"
        >
    </div>

    <ul class="max-h-56 overflow-y-auto overscroll-contain py-1" role="listbox" aria-multiselectable="true">
        <template x-for="option in filtered" :key="option.value">
            <li>
                <label class="flex cursor-pointer items-center gap-2.5 px-3 py-1.5 text-sm hover:bg-slate-50 dark:hover:bg-slate-900" :class="isSelected(option.value) && 'bg-emerald-50/60 dark:bg-emerald-500/5'">
                    <input type="checkbox" :checked="isSelected(option.value)" @change="toggle(option.value)" class="h-4 w-4 shrink-0 accent-emerald-600">
                    <span class="truncate" x-text="option.label"></span>
                </label>
            </li>
        </template>
        <li x-show="! filtered.length" class="px-3 py-4 text-center text-sm text-slate-500">No {{ $noun }} match “<span x-text="query"></span>”</li>
    </ul>

    <div class="flex items-center justify-between gap-2 border-t border-slate-200 px-3 py-1.5 text-xs dark:border-slate-800">
        <span class="text-slate-500"><span x-text="selected.length"></span> of <span x-text="options.length"></span> selected</span>
        <span class="flex items-center gap-3 font-semibold">
            <button type="button" x-show="filtered.length" @click="selectFiltered()" class="text-emerald-700 hover:underline dark:text-emerald-400" x-text="query.trim() ? `Select ${filtered.length} shown` : 'Select all'"></button>
            <button type="button" x-show="selected.length" @click="clear()" class="text-slate-500 hover:text-red-600 hover:underline">Clear</button>
        </span>
    </div>
</div>
