<div>
    @section('title', $loyaltyPackage ? 'Edit loyalty package' : 'New loyalty package')

    @php
        $fieldClass = 'h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-950 shadow-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white';
        $earningRate = is_numeric($points_per_currency_unit) ? (float) $points_per_currency_unit : 0;
        $pointValue = is_numeric($currency_value_per_point) ? (float) $currency_value_per_point : 0;
    @endphp

    <div class="mx-auto max-w-5xl space-y-6">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-medium text-emerald-700 dark:text-emerald-400">Customer relationships</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 dark:text-white">{{ $loyaltyPackage ? 'Edit loyalty package' : 'New loyalty package' }}</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Configure how enrolled customers earn and redeem points.</p>
            </div>
            <a href="{{ route('loyalty-packages.index') }}" class="inline-flex h-9 items-center gap-1.5 self-start text-sm font-semibold text-slate-600 hover:text-emerald-700 dark:text-slate-300 dark:hover:text-emerald-300 sm:self-auto">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6M9 12h12" /></svg>
                Back to packages
            </a>
        </header>

        @if (session('status'))
            <div role="status" class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">{{ session('status') }}</div>
        @endif

        <form wire:submit="save" class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(280px,0.75fr)] lg:items-start">
            <div class="space-y-5">
                <section class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <header class="border-b border-slate-200 px-5 py-4 dark:border-slate-800 sm:px-6">
                        <h3 class="text-base font-semibold text-slate-950 dark:text-white">Package identity</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">A clear name helps staff select the correct customer program.</p>
                    </header>
                    <div class="p-5 sm:p-6">
                        <label for="name" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Package name <span class="text-rose-600">*</span></label>
                        <input wire:model="name" id="name" type="text" maxlength="255" autocomplete="off" placeholder="e.g. Standard Rewards" required class="{{ $fieldClass }}">
                        @error('name') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </section>

                <section class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <header class="border-b border-slate-200 px-5 py-4 dark:border-slate-800 sm:px-6">
                        <h3 class="text-base font-semibold text-slate-950 dark:text-white">Earn and redeem</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Rates use the business currency configured for sales.</p>
                    </header>
                    <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
                        <div>
                            <label for="points_per_currency_unit" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Points earned per 1 currency unit <span class="text-rose-600">*</span></label>
                            <div class="relative">
                                <input wire:model.live.debounce.300ms="points_per_currency_unit" id="points_per_currency_unit" type="text" inputmode="decimal" autocomplete="off" placeholder="0.0000" required class="{{ $fieldClass }} pr-14 text-right font-mono tabular-nums">
                                <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-400">points</span>
                            </div>
                            <p class="mt-1.5 text-xs leading-5 text-slate-500 dark:text-slate-400">Set to zero to disable earning for this package.</p>
                            @error('points_per_currency_unit') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="currency_value_per_point" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Currency value per point redeemed <span class="text-rose-600">*</span></label>
                            <div class="relative">
                                <input wire:model.live.debounce.300ms="currency_value_per_point" id="currency_value_per_point" type="text" inputmode="decimal" autocomplete="off" placeholder="0.0000" required class="{{ $fieldClass }} pr-14 text-right font-mono tabular-nums">
                                <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-400">currency</span>
                            </div>
                            <p class="mt-1.5 text-xs leading-5 text-slate-500 dark:text-slate-400">Set to zero to disable point redemption for this package.</p>
                            @error('currency_value_per_point') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </section>

                <section class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <header class="border-b border-slate-200 px-5 py-4 dark:border-slate-800 sm:px-6">
                        <h3 class="text-base font-semibold text-slate-950 dark:text-white">Point expiry</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Choose how long awarded points remain valid.</p>
                    </header>
                    <div class="p-5 sm:p-6">
                        <label for="points_expire_after_days" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Expire points after</label>
                        <div class="relative max-w-sm">
                            <input wire:model="points_expire_after_days" id="points_expire_after_days" type="number" inputmode="numeric" min="1" step="1" placeholder="Never" class="{{ $fieldClass }} pr-16 text-right tabular-nums">
                            <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-400">days</span>
                        </div>
                        <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">Leave blank for points that do not expire.</p>
                        @error('points_expire_after_days') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </section>
            </div>

            <aside class="space-y-5 lg:sticky lg:top-20">
                <section class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <header class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                        <h3 class="text-base font-semibold text-slate-950 dark:text-white">Program preview</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Example using the rates above.</p>
                    </header>
                    <div class="space-y-4 p-5">
                        <div class="flex items-start justify-between gap-4 border-b border-slate-100 pb-4 dark:border-slate-800">
                            <div><p class="text-sm font-medium text-slate-800 dark:text-slate-200">Spend 100 currency units</p><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Points awarded</p></div>
                            <p class="shrink-0 text-right font-mono text-lg font-bold tabular-nums text-emerald-700 dark:text-emerald-300">{{ number_format($earningRate * 100, 3, '.', ',') }}<span class="ml-1 text-xs font-semibold">pts</span></p>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <div><p class="text-sm font-medium text-slate-800 dark:text-slate-200">Redeem 1,000 points</p><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Currency value</p></div>
                            <p class="shrink-0 text-right font-mono text-lg font-bold tabular-nums text-sky-700 dark:text-sky-300">{{ number_format($pointValue * 1000, 2, '.', ',') }}</p>
                        </div>
                        <div class="rounded-md bg-slate-50 px-3 py-2.5 text-xs leading-5 text-slate-600 dark:bg-slate-950 dark:text-slate-400">
                            @if ($points_expire_after_days)
                                Points expire {{ number_format((int) $points_expire_after_days) }} {{ \Illuminate\Support\Str::plural('day', (int) $points_expire_after_days) }} after they are awarded.
                            @else
                                Points do not expire.
                            @endif
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <label class="flex cursor-pointer items-start justify-between gap-4">
                        <span><span class="block text-sm font-semibold text-slate-900 dark:text-white">Package active</span><span class="mt-1 block text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $is_active ? 'Customers can earn points with this package.' : 'This package is paused for new earning.' }}</span></span>
                        <input wire:model.live="is_active" type="checkbox" class="mt-0.5 h-5 w-5 shrink-0 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 dark:border-slate-700">
                    </label>
                    @error('is_active') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                </section>
            </aside>

            <footer class="sticky bottom-0 z-10 -mx-1 flex flex-col-reverse gap-3 border-t border-slate-200 bg-white/95 px-1 py-3 backdrop-blur sm:flex-row sm:items-center sm:justify-end lg:col-span-2 dark:border-slate-800 dark:bg-slate-950/95">
                <a href="{{ route('loyalty-packages.index') }}" class="inline-flex h-10 items-center justify-center rounded-md px-4 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">Cancel</a>
                <button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-emerald-700 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-emerald-600 dark:hover:bg-emerald-500">
                    <svg wire:loading.remove wire:target="save" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 6M4 5h12l4 4v10H4z" /></svg>
                    <svg wire:loading wire:target="save" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z" /></svg>
                    <span wire:loading.remove wire:target="save">{{ $loyaltyPackage ? 'Save package' : 'Create package' }}</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </button>
            </footer>
        </form>
    </div>
</div>
