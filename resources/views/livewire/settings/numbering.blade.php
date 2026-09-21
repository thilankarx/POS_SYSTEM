<div>
    @section('title', 'Document numbering')

    <div class="mx-auto max-w-6xl space-y-5">
        <header>
            <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-400">Settings</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-950 dark:text-white">Document numbering</h1>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Control the prefixes and counters used when new business documents are created.</p>
        </header>

        @if (session('status'))
            <div role="status" class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">{{ session('status') }}</div>
        @endif

        <form wire:submit="save" class="space-y-5">
            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800 sm:px-6">
                    <h2 class="text-base font-semibold text-slate-950 dark:text-white">Sequence formats</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Preview updates as you edit each sequence.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[850px] text-left text-sm">
                        <thead class="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-400">
                            <tr>
                                <th scope="col" class="px-4 py-3">Document</th>
                                <th scope="col" class="px-4 py-3">Prefix</th>
                                <th scope="col" class="px-4 py-3">Zero-padding</th>
                                <th scope="col" class="px-4 py-3">Next value</th>
                                <th scope="col" class="px-4 py-3">Example next number</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach (\App\Livewire\Settings\Numbering::KEYS as $key => $label)
                                @php
                                    $previewPrefix = $sequences[$key]['prefix'] ?? '';
                                    $previewPadding = max(1, min(10, (int) ($sequences[$key]['padding'] ?? 1)));
                                    $previewNumber = str_pad((string) max(1, (int) ($sequences[$key]['next_value'] ?? 1)), $previewPadding, '0', STR_PAD_LEFT);
                                    $preview = implode('-', array_filter([$previewPrefix, $previewNumber]));
                                @endphp
                                <tr wire:key="document-sequence-{{ $key }}" class="transition-colors hover:bg-slate-50/70 dark:hover:bg-slate-800/30">
                                    <th scope="row" class="whitespace-nowrap px-4 py-4 font-semibold text-slate-900 dark:text-white">{{ $label }}</th>
                                    <td class="px-4 py-3.5">
                                        <label for="sequence-{{ $key }}-prefix" class="sr-only">{{ $label }} prefix</label>
                                        <input wire:model.live="sequences.{{ $key }}.prefix" id="sequence-{{ $key }}-prefix" type="text" maxlength="32" autocomplete="off" placeholder="Optional" class="h-10 w-36 rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                        @error("sequences.{$key}.prefix") <p class="mt-1.5 max-w-40 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <label for="sequence-{{ $key }}-padding" class="sr-only">{{ $label }} zero-padding digits</label>
                                        <input wire:model.live="sequences.{{ $key }}.padding" id="sequence-{{ $key }}-padding" type="number" min="1" max="10" step="1" inputmode="numeric" class="h-10 w-24 rounded-md border border-slate-300 bg-white px-3 text-sm tabular-nums text-slate-900 shadow-sm outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">1–10 digits</p>
                                        @error("sequences.{$key}.padding") <p class="mt-1.5 max-w-40 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <label for="sequence-{{ $key }}-next" class="sr-only">{{ $label }} next number</label>
                                        <input wire:model.live="sequences.{{ $key }}.next_value" id="sequence-{{ $key }}-next" type="number" min="1" step="1" inputmode="numeric" class="h-10 w-32 rounded-md border border-slate-300 bg-white px-3 text-sm tabular-nums text-slate-900 shadow-sm outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                                        @error("sequences.{$key}.next_value") <p class="mt-1.5 max-w-40 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                                    </td>
                                    <td class="px-4 py-3.5"><code class="inline-flex rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1.5 font-mono text-sm font-semibold text-slate-800 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">{{ $preview }}</code></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <aside role="note" class="flex items-start gap-3 rounded-md border border-amber-200 bg-amber-50 px-4 py-3.5 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200">
                <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 4h.01M10.3 3.9 1.8 18.5A1.7 1.7 0 003.3 21h17.4a1.7 1.7 0 001.5-2.5L13.7 3.9a2 2 0 00-3.4 0z" /></svg>
                <div><p class="font-semibold">Changing a sequence affects the next document issued.</p><p class="mt-1 leading-5 text-amber-900/80 dark:text-amber-200/80">Lowering a next value below a number already in use can create duplicate document numbers. Check existing records before changing a counter.</p></div>
            </aside>

            <footer class="sticky bottom-0 z-10 -mx-1 flex flex-col-reverse gap-3 border-t border-slate-200 bg-white/95 px-1 py-3 backdrop-blur sm:flex-row sm:items-center sm:justify-end dark:border-slate-800 dark:bg-slate-950/95">
                <button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-emerald-700 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-emerald-600 dark:hover:bg-emerald-500">
                    <svg wire:loading.remove wire:target="save" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 6M4 5h12l4 4v10H4z"/></svg>
                    <svg wire:loading wire:target="save" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"/></svg>
                    <span wire:loading.remove wire:target="save">Save numbering</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </button>
            </footer>
        </form>
    </div>
</div>
