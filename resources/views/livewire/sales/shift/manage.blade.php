<div>
    @section('title', 'Shift')

    @php
        $currency = config('pos.currency', 'LKR');
        $formatMoney = static fn ($money) => \App\Support\Money\Money::format($money);
    @endphp

    <div class="mx-auto max-w-7xl space-y-6">
        <section class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <h2 class="text-2xl font-bold text-slate-950 dark:text-white">Register shift</h2>
                    @if ($shift)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Open</span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300"><span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>Closed</span>
                    @endif
                </div>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Manage the active drawer and reconcile it at close.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @can('sales.create')
                    <a href="{{ route('pos') }}" class="inline-flex h-10 items-center gap-2 rounded-md border border-slate-300 bg-white px-3.5 text-sm font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l3-8H6.4M7 13l-2 4h13M9 21h.01M17 21h.01" /></svg>
                        Register
                    </a>
                @endcan
                @can('shifts.view_all')
                    <a href="{{ route('shift.history') }}" class="inline-flex h-10 items-center gap-2 rounded-md border border-slate-300 bg-white px-3.5 text-sm font-semibold text-slate-700 shadow-sm transition-colors hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 2m6-2a9 9 0 11-3-6.7M21 3v6h-6" /></svg>
                        Shift history
                    </a>
                @endcan
            </div>
        </section>

        @if ($terminals->isEmpty())
            <section class="rounded-lg border border-slate-200 bg-white px-6 py-14 text-center shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <span class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300"><svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 4h.01M5.1 19h13.8a2 2 0 001.73-3L13.73 4a2 2 0 00-3.46 0L3.37 16a2 2 0 001.73 3z" /></svg></span>
                <h3 class="mt-3 text-base font-semibold text-slate-950 dark:text-white">No active terminal assigned</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Ask a manager to assign an active terminal at one of your locations.</p>
            </section>
        @else
            <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
                <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                    <div>
                        <label for="terminalId" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200">Terminal</label>
                        <select wire:model.live="terminalId" id="terminalId" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            @foreach ($terminals as $availableTerminal)
                                <option value="{{ $availableTerminal->id }}">{{ $availableTerminal->name }} ({{ $availableTerminal->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    @if ($terminal)
                        <div class="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17.7 16.7L12 22l-5.7-5.3a8 8 0 1111.4 0zM12 11a2 2 0 100-4 2 2 0 000 4z" /></svg>
                            {{ $terminal->stockLocation->name }}
                        </div>
                    @endif
                </div>
            </section>

            @if ($lastClosedShift)
                <section class="overflow-hidden rounded-lg border border-emerald-200 bg-white shadow-sm dark:border-emerald-900/60 dark:bg-slate-900">
                    <div class="flex items-center justify-between border-b border-emerald-100 bg-emerald-50 px-4 py-3 dark:border-emerald-900/50 dark:bg-emerald-500/10 sm:px-5">
                        <div class="flex items-center gap-2 text-sm font-semibold text-emerald-800 dark:text-emerald-300"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>Shift closed and reconciled</div>
                        <span class="text-xs text-emerald-700 dark:text-emerald-400">{{ $lastClosedShift->closed_at->format('g:i A') }}</span>
                    </div>
                    <dl class="grid grid-cols-2 divide-x divide-y divide-slate-200 dark:divide-slate-800 sm:grid-cols-5 sm:divide-y-0">
                        <div class="p-4"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Opening float</dt><dd class="mt-1.5 font-semibold tabular-nums text-slate-950 dark:text-white">{{ $lastClosedShift->opening_float }}</dd></div>
                        <div class="p-4"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Expected cash</dt><dd class="mt-1.5 font-semibold tabular-nums text-slate-950 dark:text-white">{{ $lastClosedShift->expected_cash }}</dd></div>
                        <div class="p-4"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Counted cash</dt><dd class="mt-1.5 font-semibold tabular-nums text-slate-950 dark:text-white">{{ $lastClosedShift->counted_cash }}</dd></div>
                        <div class="p-4"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Variance</dt><dd class="mt-1.5 font-semibold tabular-nums {{ $lastClosedShift->cash_variance->isZero() ? 'text-emerald-700 dark:text-emerald-400' : ($lastClosedShift->cash_variance->isNegative() ? 'text-red-600 dark:text-red-400' : 'text-amber-700 dark:text-amber-400') }}">{{ $lastClosedShift->cash_variance }}</dd></div>
                        <div class="p-4"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Cash dropped</dt><dd class="mt-1.5 font-semibold tabular-nums text-slate-950 dark:text-white">{{ $lastClosedShift->cash_dropped }}</dd></div>
                    </dl>
                </section>
            @endif

            @if ($shift === null)
                <div class="grid gap-6 lg:grid-cols-3">
                    <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6 lg:col-span-2">
                        <div class="flex items-start gap-3">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-md bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg></span>
                            <div><h3 class="text-base font-semibold text-slate-950 dark:text-white">Open this terminal</h3><p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Enter the cash already in the drawer before taking the first sale.</p></div>
                        </div>

                        <form wire:submit="open" class="mt-6 space-y-5">
                            <div>
                                <label for="openingFloat" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200">Opening float</label>
                                <div class="flex max-w-md overflow-hidden rounded-md border border-slate-300 bg-white shadow-sm focus-within:border-emerald-500 focus-within:ring-1 focus-within:ring-emerald-500 dark:border-slate-700 dark:bg-slate-950">
                                    <span class="flex items-center border-r border-slate-300 bg-slate-50 px-3 text-xs font-semibold text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">{{ $currency }}</span>
                                    <input wire:model="openingFloat" id="openingFloat" type="text" inputmode="decimal" class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2.5 text-right text-sm tabular-nums outline-none focus:ring-0">
                                </div>
                                @error('openingFloat') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="open-note" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200">Opening note <span class="font-normal text-slate-400">(optional)</span></label>
                                <input wire:model="note" id="open-note" type="text" maxlength="1000" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-950">
                            </div>
                            <button type="submit" wire:loading.attr="disabled" wire:target="open" class="inline-flex h-10 items-center gap-2 rounded-md bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60">
                                <svg wire:loading.remove wire:target="open" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6" /></svg>
                                <span wire:loading.remove wire:target="open">Open shift</span><span wire:loading wire:target="open">Opening&hellip;</span>
                            </button>
                        </form>
                    </section>

                    <aside class="rounded-lg border border-slate-200 bg-slate-50 p-5 dark:border-slate-800 dark:bg-slate-900/60">
                        <h3 class="text-sm font-semibold text-slate-950 dark:text-white">Terminal details</h3>
                        <dl class="mt-4 space-y-4 text-sm">
                            <div><dt class="text-xs uppercase text-slate-500 dark:text-slate-400">Terminal</dt><dd class="mt-1 font-semibold text-slate-900 dark:text-white">{{ $terminal->name }}</dd></div>
                            <div><dt class="text-xs uppercase text-slate-500 dark:text-slate-400">Code</dt><dd class="mt-1 font-medium text-slate-700 dark:text-slate-300">{{ $terminal->code }}</dd></div>
                            <div><dt class="text-xs uppercase text-slate-500 dark:text-slate-400">Location</dt><dd class="mt-1 font-medium text-slate-700 dark:text-slate-300">{{ $terminal->stockLocation->name }}</dd></div>
                        </dl>
                    </aside>
                </div>
            @else
                <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <dl class="grid grid-cols-2 divide-x divide-y divide-slate-200 dark:divide-slate-800 lg:grid-cols-4 lg:divide-y-0">
                        <div class="p-4 sm:p-5"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Opened</dt><dd class="mt-2 font-semibold text-slate-950 dark:text-white">{{ $shift->opened_at->format('g:i A') }}</dd><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $shift->opened_at->diffForHumans() }}</p></div>
                        <div class="p-4 sm:p-5"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Opening float</dt><dd class="mt-2 text-xl font-bold tabular-nums text-slate-950 dark:text-white">{{ $formatMoney($shift->opening_float) }}</dd><p class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400">By {{ $shift->openedBy->name }}</p></div>
                        <div class="p-4 sm:p-5"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Shift sales</dt><dd class="mt-2 text-xl font-bold tabular-nums text-slate-950 dark:text-white">{{ $formatMoney($shiftSales->total) }}</dd><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Gross completed sales</p></div>
                        <div class="p-4 sm:p-5"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Transactions</dt><dd class="mt-2 text-xl font-bold tabular-nums text-slate-950 dark:text-white">{{ number_format($shiftSales->sale_count) }}</dd><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Completed this shift</p></div>
                    </dl>
                </section>

                <div class="grid gap-6 lg:grid-cols-3">
                    <section class="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900 lg:col-span-2">
                        <div class="border-b border-slate-200 px-4 py-4 dark:border-slate-800 sm:px-5"><h3 class="text-base font-semibold text-slate-950 dark:text-white">Count the drawer</h3><p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Enter the number of notes or coins for each denomination.</p></div>
                        <div class="grid grid-cols-2 gap-px bg-slate-200 dark:bg-slate-800 sm:grid-cols-3 xl:grid-cols-5">
                            @foreach ($cashCounts as $denomination => $count)
                                <label class="bg-white p-3 dark:bg-slate-900" wire:key="denomination-{{ $denomination }}">
                                    <span class="block text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $currency }} {{ number_format((float) $denomination, fmod((float) $denomination, 1.0) === 0.0 ? 0 : 2) }}</span>
                                    <input wire:model.live.debounce.250ms="cashCounts.{{ $denomination }}" type="number" min="0" step="1" inputmode="numeric" aria-label="Count of {{ $currency }} {{ $denomination }}" class="mt-2 h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-right text-sm font-semibold tabular-nums shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-950">
                                </label>
                            @endforeach
                        </div>
                    </section>

                    <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Counted cash</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-slate-950 dark:text-white">{{ $formatMoney($countedCash) }}</p>
                        <div class="my-5 border-t border-slate-200 dark:border-slate-800"></div>

                        <form wire:submit="close" class="space-y-4">
                            <div>
                                <label for="countedNonCash" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200">Counted non-cash <span class="font-normal text-slate-400">(optional)</span></label>
                                <div class="flex overflow-hidden rounded-md border border-slate-300 bg-white shadow-sm focus-within:border-emerald-500 focus-within:ring-1 focus-within:ring-emerald-500 dark:border-slate-700 dark:bg-slate-950">
                                    <span class="flex items-center border-r border-slate-300 bg-slate-50 px-3 text-xs font-semibold text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">{{ $currency }}</span>
                                    <input wire:model="countedNonCash" id="countedNonCash" type="text" inputmode="decimal" placeholder="0.00" class="min-w-0 flex-1 border-0 bg-transparent px-3 py-2.5 text-right text-sm tabular-nums outline-none focus:ring-0">
                                </div>
                                @error('countedNonCash') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="close-note" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200">Closing note <span class="font-normal text-slate-400">(optional)</span></label>
                                <textarea wire:model="note" id="close-note" rows="3" maxlength="1000" class="w-full resize-none rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-950"></textarea>
                                @error('note') <p class="mt-1.5 text-sm text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            </div>
                            <button type="submit" wire:confirm="Close this shift and reconcile the drawer?" wire:loading.attr="disabled" wire:target="close" class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-md bg-red-600 px-4 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60">
                                <svg wire:loading.remove wire:target="close" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M5 12l6-6M5 12l6 6" /></svg>
                                <span wire:loading.remove wire:target="close">Close and reconcile</span><span wire:loading wire:target="close">Closing&hellip;</span>
                            </button>
                        </form>
                    </section>
                </div>

                @can('cash.movement')
                    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex flex-col gap-3 border-b border-slate-200 px-4 py-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                            <div><h3 class="text-base font-semibold text-slate-950 dark:text-white">Cash movements</h3><p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">Petty cash and safe drops recorded against this shift.</p></div>
                            <div class="flex gap-4 text-xs"><span class="text-slate-500 dark:text-slate-400">Cash in <strong class="ml-1 text-emerald-700 dark:text-emerald-400">{{ $formatMoney($cashIn) }}</strong></span><span class="text-slate-500 dark:text-slate-400">Cash out <strong class="ml-1 text-red-600 dark:text-red-400">{{ $formatMoney($cashOut) }}</strong></span></div>
                        </div>

                        <form wire:submit="recordCashMovement" class="grid gap-4 border-b border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/40 sm:grid-cols-2 lg:grid-cols-[auto_12rem_minmax(14rem,1fr)_auto] lg:items-end sm:p-5">
                            <fieldset>
                                <legend class="mb-1.5 text-sm font-semibold text-slate-700 dark:text-slate-200">Direction</legend>
                                <div class="inline-flex h-10 rounded-md border border-slate-300 bg-white p-1 shadow-sm dark:border-slate-700 dark:bg-slate-950">
                                    <label class="cursor-pointer"><input wire:model="movementDirection" type="radio" value="out" class="peer sr-only"><span class="flex h-8 items-center rounded px-3 text-sm font-medium text-slate-600 peer-checked:bg-red-50 peer-checked:text-red-700 dark:text-slate-300 dark:peer-checked:bg-red-500/10 dark:peer-checked:text-red-300">Cash out</span></label>
                                    <label class="cursor-pointer"><input wire:model="movementDirection" type="radio" value="in" class="peer sr-only"><span class="flex h-8 items-center rounded px-3 text-sm font-medium text-slate-600 peer-checked:bg-emerald-50 peer-checked:text-emerald-700 dark:text-slate-300 dark:peer-checked:bg-emerald-500/10 dark:peer-checked:text-emerald-300">Cash in</span></label>
                                </div>
                            </fieldset>
                            <div>
                                <label for="movementAmount" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200">Amount</label>
                                <div class="flex h-10 overflow-hidden rounded-md border border-slate-300 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-950"><span class="flex items-center border-r border-slate-300 bg-slate-50 px-2.5 text-xs font-semibold text-slate-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400">{{ $currency }}</span><input wire:model="movementAmount" id="movementAmount" type="text" inputmode="decimal" class="min-w-0 flex-1 border-0 bg-transparent px-3 text-right text-sm tabular-nums focus:ring-0"></div>
                                @error('movementAmount') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="movementReason" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200">Reason</label>
                                <input wire:model="movementReason" id="movementReason" type="text" maxlength="255" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500 dark:border-slate-700 dark:bg-slate-950">
                                @error('movementReason') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                            </div>
                            <button type="submit" wire:loading.attr="disabled" wire:target="recordCashMovement" class="inline-flex h-10 items-center justify-center rounded-md bg-slate-900 px-4 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 disabled:opacity-60 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200"><span wire:loading.remove wire:target="recordCashMovement">Record</span><span wire:loading wire:target="recordCashMovement">Recording&hellip;</span></button>
                        </form>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-slate-950/50 dark:text-slate-400"><tr><th class="px-4 py-2.5 font-semibold sm:px-5">Direction</th><th class="px-4 py-2.5 font-semibold">Reason</th><th class="px-4 py-2.5 font-semibold">Recorded by</th><th class="px-4 py-2.5 font-semibold">Time</th><th class="px-4 py-2.5 text-right font-semibold sm:px-5">Amount</th></tr></thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    @forelse ($cashMovements as $movement)
                                        <tr wire:key="movement-{{ $movement->id }}" class="hover:bg-slate-50 dark:hover:bg-slate-800/50">
                                            <td class="whitespace-nowrap px-4 py-3 sm:px-5"><span class="rounded px-2 py-1 text-xs font-semibold {{ $movement->direction === 'in' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300' }}">{{ $movement->direction === 'in' ? 'Cash in' : 'Cash out' }}</span></td>
                                            <td class="max-w-sm truncate px-4 py-3 text-slate-700 dark:text-slate-300">{{ $movement->reason }}</td>
                                            <td class="whitespace-nowrap px-4 py-3 text-slate-500 dark:text-slate-400">{{ $movement->user?->name ?? 'Unknown' }}</td>
                                            <td class="whitespace-nowrap px-4 py-3 text-slate-500 dark:text-slate-400">{{ $movement->created_at->format('g:i A') }}</td>
                                            <td class="whitespace-nowrap px-4 py-3 text-right font-semibold tabular-nums text-slate-900 dark:text-white sm:px-5">{{ $movement->amount }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-400">No cash movements recorded this shift.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endcan
            @endif
        @endif
    </div>
</div>
