<div>
    @section('title', 'Label printer')

    @php
        $previewWidth = max(1, (int) $width_mm);
        $previewHeight = max(1, (int) $height_mm);
        $previewScale = max($previewWidth, $previewHeight);
        $previewWidthPx = round(360 * $previewWidth / $previewScale);
    @endphp

    <div class="mx-auto max-w-6xl space-y-5">
        <header>
            <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-400">Settings</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-950 dark:text-white">Label printer</h1>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Set the stock dimensions and print density used for barcode labels.</p>
        </header>

        @if (session('status'))
            <div role="status" class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">{{ session('status') }}</div>
        @endif

        <form wire:submit="save" class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(280px,0.7fr)] lg:items-start">
            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800 sm:px-6">
                    <h2 class="text-base font-semibold text-slate-950 dark:text-white">Stock and print settings</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">These dimensions apply to barcode labels throughout the store.</p>
                </div>
                <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
                    <div>
                        <label for="width_mm" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Label width <span class="text-rose-600">*</span></label>
                        <div class="relative"><input wire:model.live="width_mm" id="width_mm" type="number" min="1" max="200" step="1" inputmode="numeric" required class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 pr-12 text-sm tabular-nums text-slate-900 shadow-sm outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white"><span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs font-medium text-slate-400">mm</span></div>
                        @error('width_mm') <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="height_mm" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Label height <span class="text-rose-600">*</span></label>
                        <div class="relative"><input wire:model.live="height_mm" id="height_mm" type="number" min="1" max="200" step="1" inputmode="numeric" required class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 pr-12 text-sm tabular-nums text-slate-900 shadow-sm outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white"><span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs font-medium text-slate-400">mm</span></div>
                        @error('height_mm') <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="gap_mm" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Gap between labels</label>
                        <div class="relative"><input wire:model.live="gap_mm" id="gap_mm" type="number" min="0" max="50" step="1" inputmode="numeric" required class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 pr-12 text-sm tabular-nums text-slate-900 shadow-sm outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white"><span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs font-medium text-slate-400">mm</span></div>
                        @error('gap_mm') <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="density" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Print density</label>
                        <div class="relative"><input wire:model.live="density" id="density" type="number" min="1" max="15" step="1" inputmode="numeric" required class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 pr-20 text-sm tabular-nums text-slate-900 shadow-sm outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white"><span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs font-medium text-slate-400">1–15</span></div>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Higher values print darker.</p>
                        @error('density') <p class="mt-1.5 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <aside class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-4 py-3 dark:border-slate-800"><h2 class="text-sm font-semibold text-slate-950 dark:text-white">Stock preview</h2><p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Proportional to the selected dimensions</p></div>
                <div class="flex min-h-64 flex-col items-center justify-center overflow-hidden bg-slate-50 p-5 dark:bg-slate-950/50">
                    <div class="flex max-w-full items-center justify-center">
                        <div class="flex max-w-full flex-col items-center justify-center overflow-hidden rounded border border-slate-300 bg-white p-4 shadow-sm dark:border-slate-600" style="width: min(100%, {{ $previewWidthPx }}px); aspect-ratio: {{ $previewWidth }} / {{ $previewHeight }};">
                            <div aria-hidden="true" class="h-8 w-4/5 rounded-sm" style="opacity: {{ max(0.2, min(1, $density / 10)) }}; background: repeating-linear-gradient(90deg, #111827 0 2px, transparent 2px 4px, #111827 4px 5px, transparent 5px 8px, #111827 8px 12px, transparent 12px 14px, #111827 14px 15px, transparent 15px 18px);"></div>
                            <div class="mt-2 max-w-full truncate font-mono text-[10px] text-slate-700">0123456789</div>
                            <div class="mt-1 h-1 w-2/3 rounded bg-slate-300"></div>
                        </div>
                    </div>
                    <div class="mt-4 flex w-full max-w-sm items-center justify-center gap-2 text-xs text-slate-500 dark:text-slate-400"><span class="h-px flex-1 border-t border-dashed border-slate-400"></span><span>{{ $previewWidth }} × {{ $previewHeight }} mm</span><span class="h-px flex-1 border-t border-dashed border-slate-400"></span></div>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ $gap_mm }} mm gap · Density {{ $density }}/15</p>
                </div>
            </aside>

            <footer class="sticky bottom-0 z-10 -mx-1 flex justify-end border-t border-slate-200 bg-white/95 px-1 py-3 backdrop-blur lg:col-span-2 dark:border-slate-800 dark:bg-slate-950/95">
                <button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-emerald-700 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-emerald-600 dark:hover:bg-emerald-500">
                    <svg wire:loading.remove wire:target="save" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 6M4 5h12l4 4v10H4z" /></svg>
                    <svg wire:loading wire:target="save" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z" /></svg>
                    <span wire:loading.remove wire:target="save">Save label settings</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </button>
            </footer>
        </form>
    </div>
</div>
