<div>
    @section('title', 'Tax defaults')

    <div class="mx-auto max-w-5xl space-y-5">
        <header>
            <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-400">Settings</p>
            <h1 class="mt-1 text-2xl font-bold text-slate-950 dark:text-white">Tax defaults</h1>
            <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Choose how catalog prices are treated when tax is calculated at checkout.</p>
        </header>

        @if (session('status'))
            <div role="status" class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">{{ session('status') }}</div>
        @endif

        <form wire:submit="save" class="space-y-5">
            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                    <div class="max-w-2xl">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-base font-semibold text-slate-950 dark:text-white">Catalog price includes tax</h2>
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $prices_include_tax ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}">{{ $prices_include_tax ? 'Inclusive' : 'Exclusive' }}</span>
                        </div>
                        <p class="mt-1.5 text-sm leading-5 text-slate-600 dark:text-slate-400">{{ $prices_include_tax ? 'The entered item price is treated as the customer-facing total. Tax is separated from that amount for the tax breakdown.' : 'Tax is calculated on top of the entered item price and added to the customer-facing total.' }}</p>
                    </div>

                    <label for="prices_include_tax" class="inline-flex min-h-11 shrink-0 cursor-pointer items-center gap-3 self-start rounded-md border border-slate-300 bg-white px-3.5 py-2.5 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-950 dark:hover:bg-slate-800 sm:self-auto">
                        <input wire:model.live="prices_include_tax" id="prices_include_tax" type="checkbox" role="switch" aria-checked="{{ $prices_include_tax ? 'true' : 'false' }}" class="peer sr-only">
                        <span aria-hidden="true" class="relative h-5 w-9 rounded-full bg-slate-300 transition peer-checked:bg-emerald-600 peer-focus-visible:ring-2 peer-focus-visible:ring-emerald-600 peer-focus-visible:ring-offset-2 dark:bg-slate-700 dark:peer-checked:bg-emerald-500 dark:peer-focus-visible:ring-offset-slate-950"><span class="absolute left-0.5 top-0.5 h-4 w-4 rounded-full bg-white shadow-sm transition {{ $prices_include_tax ? 'translate-x-4' : '' }}"></span></span>
                        <span class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $prices_include_tax ? 'Tax included' : 'Add tax at checkout' }}</span>
                    </label>
                </div>

                <div class="border-t border-slate-200 dark:border-slate-800">
                    <div class="grid divide-y divide-slate-200 dark:divide-slate-800 md:grid-cols-2 md:divide-x md:divide-y-0">
                        <div class="p-5 sm:p-6">
                            <p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Exclusive pricing</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900 dark:text-white">Entered price + tax = total</p>
                            <div class="mt-4 flex items-center gap-2 text-sm tabular-nums">
                                <span class="rounded-md bg-slate-100 px-3 py-2 font-semibold text-slate-800 dark:bg-slate-800 dark:text-slate-200">100.00</span>
                                <span class="text-slate-400">+</span>
                                <span class="rounded-md bg-slate-100 px-3 py-2 font-semibold text-slate-800 dark:bg-slate-800 dark:text-slate-200">10.00 tax</span>
                                <span class="text-slate-400">=</span>
                                <span class="rounded-md bg-slate-100 px-3 py-2 font-bold text-slate-900 dark:bg-slate-800 dark:text-white">110.00</span>
                            </div>
                        </div>
                        <div class="p-5 sm:p-6">
                            <p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Inclusive pricing</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900 dark:text-white">Entered total, tax separated</p>
                            <div class="mt-4 flex items-center gap-2 text-sm tabular-nums">
                                <span class="rounded-md bg-emerald-50 px-3 py-2 font-bold text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300">110.00 total</span>
                                <span class="text-slate-400">=</span>
                                <span class="rounded-md bg-slate-100 px-3 py-2 font-semibold text-slate-800 dark:bg-slate-800 dark:text-slate-200">100.00 net</span>
                                <span class="text-slate-400">+</span>
                                <span class="rounded-md bg-slate-100 px-3 py-2 font-semibold text-slate-800 dark:bg-slate-800 dark:text-slate-200">10.00 tax</span>
                            </div>
                        </div>
                    </div>
                    <p class="border-t border-slate-200 bg-slate-50 px-5 py-3 text-xs text-slate-500 dark:border-slate-800 dark:bg-slate-950/50 dark:text-slate-400">Illustration uses a simple 10% rate. Actual tax is calculated from the configured category rates.</p>
                </div>
            </section>

            <section class="flex flex-col gap-3 rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <div>
                    <h2 class="text-sm font-semibold text-slate-900 dark:text-white">Tax categories and rates</h2>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Configure categories, effective dates, and any cascading rates separately.</p>
                </div>
                <a href="{{ route('tax-categories.index') }}" class="inline-flex h-9 shrink-0 items-center justify-center gap-2 self-start rounded-md border border-slate-300 px-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800 sm:self-auto">
                    Manage tax categories
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6" /></svg>
                </a>
            </section>

            <footer class="sticky bottom-0 z-10 -mx-1 flex justify-end border-t border-slate-200 bg-white/95 px-1 py-3 backdrop-blur dark:border-slate-800 dark:bg-slate-950/95">
                <button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-emerald-700 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-emerald-600 dark:hover:bg-emerald-500">
                    <svg wire:loading.remove wire:target="save" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 6M4 5h12l4 4v10H4z" /></svg>
                    <svg wire:loading wire:target="save" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z" /></svg>
                    <span wire:loading.remove wire:target="save">Save tax defaults</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </button>
            </footer>
        </form>
    </div>
</div>
