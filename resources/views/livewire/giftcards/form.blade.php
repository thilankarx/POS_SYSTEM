<div>
    @section('title', $giftcard ? 'Gift card '.$giftcard->number : 'Issue gift card')

    @php
        $fieldClass = 'h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-950 shadow-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white';
        $initialAmount = is_numeric($initial_value) ? number_format((float) $initial_value, 2, '.', ',') : '0.00';
        $topUpPreview = is_numeric($topUpAmount) ? number_format((float) $topUpAmount, 2, '.', ',') : null;
        $selectedCustomer = $customers->firstWhere('id', $customer_id);
    @endphp

    <div class="mx-auto max-w-6xl space-y-6">
        @if (! $giftcard)
            <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-medium text-emerald-700 dark:text-emerald-400">Customer relationships</p>
                    <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 dark:text-white">Issue gift card</h2>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Create a card with an initial balance and optional customer and expiry.</p>
                </div>
                <a href="{{ route('giftcards.index') }}" class="inline-flex h-9 items-center gap-1.5 self-start text-sm font-semibold text-slate-600 hover:text-emerald-700 dark:text-slate-300 dark:hover:text-emerald-300 sm:self-auto">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6M9 12h12" /></svg>
                    Back to gift cards
                </a>
            </header>

            @if (session('status'))
                <div role="status" class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">{{ session('status') }}</div>
            @endif

            <form wire:submit="issue" class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(280px,0.7fr)] lg:items-start">
                <section class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <header class="border-b border-slate-200 px-5 py-4 dark:border-slate-800 sm:px-6"><h3 class="text-base font-semibold text-slate-950 dark:text-white">Card details</h3><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">The card number is generated automatically after issue.</p></header>
                    <div class="space-y-5 p-5 sm:p-6">
                        <div>
                            <label for="initial_value" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Initial balance <span class="text-rose-600">*</span></label>
                            <div class="relative max-w-sm"><input wire:model.live.debounce.300ms="initial_value" id="initial_value" type="text" inputmode="decimal" autocomplete="off" placeholder="0.00" required class="{{ $fieldClass }} pr-16 text-right font-mono tabular-nums"><span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-400">{{ \App\Support\Money\Money::currency() }}</span></div>
                            <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">Enter an amount in the business currency. Up to two decimal places.</p>
                            @error('initial_value') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="customer_id" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Assign to customer <span class="font-normal text-slate-500">(optional)</span></label>
                            <select wire:model.live="customer_id" id="customer_id" class="{{ $fieldClass }}">
                                <option value="">Unassigned</option>
                                @foreach ($customers as $customer)
                                    <option value="{{ $customer->id }}">{{ $customer->company_name ?: $customer->person?->full_name }}{{ $customer->account_number ? ' · '.$customer->account_number : '' }}</option>
                                @endforeach
                            </select>
                            @error('customer_id') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                            <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">You can issue a card without assigning it to a customer.</p>
                        </div>
                        <div>
                            <label for="expires_at" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Expiry date <span class="font-normal text-slate-500">(optional)</span></label>
                            <input wire:model.live="expires_at" id="expires_at" type="date" class="{{ $fieldClass }} max-w-sm">
                            @error('expires_at') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                            <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">Leave blank if the card should not expire.</p>
                        </div>
                    </div>
                    <footer class="flex flex-col-reverse gap-3 border-t border-slate-200 bg-slate-50/70 px-5 py-4 sm:flex-row sm:items-center sm:justify-end dark:border-slate-800 dark:bg-slate-950/40 sm:px-6">
                        <a href="{{ route('giftcards.index') }}" class="inline-flex h-10 items-center justify-center rounded-md px-4 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">Cancel</a>
                        <button type="submit" wire:loading.attr="disabled" wire:target="issue" class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-emerald-700 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-emerald-600 dark:hover:bg-emerald-500">
                            <svg wire:loading.remove wire:target="issue" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" /></svg><svg wire:loading wire:target="issue" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z" /></svg>
                            <span wire:loading.remove wire:target="issue">Issue gift card</span><span wire:loading wire:target="issue">Issuing…</span>
                        </button>
                    </footer>
                </section>

                <aside class="space-y-4 lg:sticky lg:top-20">
                    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                        <header class="border-b border-slate-200 px-4 py-3.5 dark:border-slate-800"><h3 class="text-sm font-semibold text-slate-900 dark:text-white">Gift card preview</h3><p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Review the issue details before creating the card.</p></header>
                        <div class="p-4">
                            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-950">
                                <div class="flex items-center justify-between gap-3"><span class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Gift card</span><span class="rounded-md bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300">Ready to issue</span></div>
                                <p class="mt-5 text-xs text-slate-500 dark:text-slate-400">Initial balance</p>
                                <p class="mt-1 font-mono text-3xl font-bold tabular-nums text-slate-950 dark:text-white">{{ $initialAmount }} <span class="text-sm font-semibold">{{ \App\Support\Money\Money::currency() }}</span></p>
                                <dl class="mt-5 space-y-3 border-t border-slate-200 pt-4 text-sm dark:border-slate-700">
                                    <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Customer</dt><dd class="max-w-[65%] truncate text-right font-medium text-slate-800 dark:text-slate-200">{{ $selectedCustomer?->company_name ?: ($selectedCustomer?->person?->full_name ?? 'Unassigned') }}</dd></div>
                                    <div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Expires</dt><dd class="text-right font-medium text-slate-800 dark:text-slate-200">{{ $expires_at ? \Illuminate\Support\Carbon::parse($expires_at)->format('M j, Y') : 'Never' }}</dd></div>
                                </dl>
                            </div>
                            <p class="mt-3 text-xs leading-5 text-slate-500 dark:text-slate-400">A unique card number is created after issuing. The initial balance is recorded as the first ledger transaction.</p>
                        </div>
                    </section>
                </aside>
            </form>
        @else
            @php
                $isExpired = $giftcard->expires_at && $giftcard->expires_at->isPast();
                $isDepleted = $giftcard->balance->isZero();
                $cardState = ! $giftcard->is_active ? 'Inactive' : ($isExpired ? 'Expired' : ($isDepleted ? 'Depleted' : 'Available'));
                $cardStateClass = match ($cardState) {
                    'Available' => 'bg-emerald-50 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300',
                    'Expired' => 'bg-amber-50 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300',
                    'Depleted' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
                    default => 'bg-rose-50 text-rose-800 dark:bg-rose-500/10 dark:text-rose-300',
                };
            @endphp
            <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <a href="{{ route('giftcards.index') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 transition hover:text-emerald-700 dark:text-slate-400 dark:hover:text-emerald-300"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6M9 12h12" /></svg>Gift cards</a>
                    <div class="mt-2 flex flex-wrap items-center gap-2.5"><h2 class="font-mono text-2xl font-bold tracking-tight text-slate-950 dark:text-white">{{ $giftcard->number }}</h2><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $cardStateClass }}">{{ $cardState }}</span></div>
                </div>
            </header>

            @if (session('status'))
                <div role="status" class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">{{ session('status') }}</div>
            @endif

            <section aria-label="Gift card balance" class="grid grid-cols-1 divide-y divide-slate-200 rounded-lg border border-slate-200 bg-white sm:grid-cols-3 sm:divide-x sm:divide-y-0 dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
                <div class="px-5 py-4"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Available balance</p><p class="mt-1 text-2xl font-bold tabular-nums text-emerald-700 dark:text-emerald-300">{{ $giftcard->balance }} <span class="text-sm">{{ $giftcard->currency }}</span></p></div>
                <div class="px-5 py-4"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Initial value</p><p class="mt-1 text-2xl font-bold tabular-nums text-slate-950 dark:text-white">{{ $giftcard->initial_value }} <span class="text-sm">{{ $giftcard->currency }}</span></p></div>
                <div class="px-5 py-4"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Customer</p><p class="mt-1 truncate text-lg font-semibold text-slate-900 dark:text-white">{{ $giftcard->customer?->company_name ?: $giftcard->customer?->person?->full_name ?: 'Unassigned' }}</p></div>
            </section>

            <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(280px,0.7fr)] lg:items-start">
                <section class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <header class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h3 class="text-base font-semibold text-slate-950 dark:text-white">Transaction history</h3><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Ledger of issues, redemptions, refunds, and top-ups.</p></header>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-left text-sm">
                            <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500 dark:bg-slate-950/60 dark:text-slate-400"><tr><th class="px-4 py-3 sm:px-5">Date</th><th class="px-4 py-3">Type</th><th class="px-4 py-3 text-right">Amount</th><th class="px-4 py-3 text-right">Balance after</th></tr></thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                @forelse ($transactions as $transaction)
                                    <tr><td class="whitespace-nowrap px-4 py-3.5 text-xs text-slate-600 dark:text-slate-400 sm:px-5">{{ $transaction->created_at->format('M j, Y · g:i A') }}</td><td class="px-4 py-3.5 font-medium capitalize text-slate-800 dark:text-slate-200">{{ str($transaction->type)->replace('_', ' ') }}</td><td class="px-4 py-3.5 text-right tabular-nums text-slate-700 dark:text-slate-300">{{ $transaction->amount }}</td><td class="px-4 py-3.5 text-right font-semibold tabular-nums text-slate-900 dark:text-white">{{ $transaction->balance_after }}</td></tr>
                                @empty
                                    <tr><td colspan="4" class="px-5 py-12 text-center text-sm text-slate-500 dark:text-slate-400">No transactions recorded.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <aside class="space-y-5 lg:sticky lg:top-20">
                    @can('update', $giftcard)
                        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                            <header class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h3 class="text-base font-semibold text-slate-950 dark:text-white">Top up card</h3><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Add value to this gift card balance.</p></header>
                            <form wire:submit="topUp" class="space-y-4 p-5">
                                <div><label for="topUpAmount" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Top-up amount <span class="text-rose-600">*</span></label><div class="relative"><input wire:model.live.debounce.300ms="topUpAmount" id="topUpAmount" type="text" inputmode="decimal" placeholder="0.00" required class="{{ $fieldClass }} pr-16 text-right font-mono tabular-nums"><span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-slate-400">{{ $giftcard->currency }}</span></div>@error('topUpAmount')<p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p>@enderror</div>
                                <div class="rounded-md bg-slate-50 px-3.5 py-3 dark:bg-slate-950"><div class="flex justify-between gap-3 text-sm"><span class="text-slate-500 dark:text-slate-400">New balance</span><span class="font-semibold tabular-nums text-slate-900 dark:text-white">{{ $topUpPreview ? $giftcard->balance->plus(\App\Support\Money\Money::of($topUpAmount))->getAmount() : $giftcard->balance->getAmount() }} {{ $giftcard->currency }}</span></div></div>
                                <button type="submit" wire:loading.attr="disabled" wire:target="topUp" @disabled(! $giftcard->is_active || $isExpired) class="inline-flex h-10 w-full items-center justify-center gap-2 rounded-md bg-emerald-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-emerald-600 dark:hover:bg-emerald-500"><span wire:loading.remove wire:target="topUp">Add to balance</span><span wire:loading wire:target="topUp">Adding…</span></button>
                                @if (! $giftcard->is_active || $isExpired)<p class="text-xs text-amber-700 dark:text-amber-300">{{ ! $giftcard->is_active ? 'Inactive cards cannot be topped up.' : 'Expired cards cannot be topped up.' }}</p>@endif
                            </form>
                        </section>
                    @endcan
                    <section class="rounded-lg border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                        <h3 class="text-sm font-semibold text-slate-900 dark:text-white">Card details</h3>
                        <dl class="mt-3 space-y-3 text-sm"><div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Currency</dt><dd class="font-medium text-slate-800 dark:text-slate-200">{{ $giftcard->currency }}</dd></div><div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Expires</dt><dd class="font-medium text-slate-800 dark:text-slate-200">{{ $giftcard->expires_at?->format('M j, Y') ?? 'Never' }}</dd></div><div class="flex justify-between gap-3"><dt class="text-slate-500 dark:text-slate-400">Issued</dt><dd class="font-medium text-slate-800 dark:text-slate-200">{{ $giftcard->created_at->format('M j, Y') }}</dd></div></dl>
                    </section>
                </aside>
            </div>
        @endif
    </div>
</div>
