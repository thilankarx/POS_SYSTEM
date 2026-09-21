<div>
    @section('title', 'Gift Cards')

    <div class="mx-auto max-w-7xl space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-medium text-emerald-700 dark:text-emerald-400">Customer relationships</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 dark:text-white">Gift cards</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Track card balances, holders, and expiration status.</p>
            </div>
            @can('create', \App\Domain\Giftcards\Models\Giftcard::class)
                <a href="{{ route('giftcards.create') }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-emerald-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 dark:bg-emerald-600 dark:hover:bg-emerald-500">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
                    Issue gift card
                </a>
            @endcan
        </header>

        @if (session('status'))
            <div role="status" class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div role="alert" class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300">{{ session('error') }}</div>
        @endif

        <section aria-label="Gift card summary" class="grid grid-cols-2 divide-x divide-y divide-slate-200 overflow-hidden rounded-lg border border-slate-200 bg-white sm:grid-cols-4 sm:divide-y-0 dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
            <div class="px-5 py-4"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">All cards</p><p class="mt-1 text-2xl font-bold tabular-nums text-slate-950 dark:text-white">{{ number_format($stats['total']) }}</p></div>
            <div class="px-5 py-4"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Available</p><p class="mt-1 text-2xl font-bold tabular-nums text-emerald-700 dark:text-emerald-300">{{ number_format($stats['available']) }}</p></div>
            <div class="px-5 py-4"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Expired</p><p class="mt-1 text-2xl font-bold tabular-nums text-amber-700 dark:text-amber-300">{{ number_format($stats['expired']) }}</p></div>
            <div class="px-5 py-4"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Depleted</p><p class="mt-1 text-2xl font-bold tabular-nums text-slate-700 dark:text-slate-200">{{ number_format($stats['depleted']) }}</p></div>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-3 border-b border-slate-200 p-4 dark:border-slate-800">
                <div class="flex flex-col gap-2 lg:flex-row">
                    <label class="relative block min-w-0 flex-1">
                        <span class="sr-only">Search gift cards</span>
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7" /><path stroke-linecap="round" d="m20 20-4-4" /></svg>
                        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Card number, customer, email, or phone" class="h-10 w-full rounded-md border border-slate-300 bg-white pl-9 pr-3 text-sm text-slate-950 outline-none transition placeholder:text-slate-400 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    </label>
                    <label class="lg:w-48">
                        <span class="sr-only">Filter gift cards by status</span>
                        <select wire:model.live="status" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-700 outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200">
                            <option value="">All statuses</option><option value="available">Available</option><option value="expired">Expired</option><option value="depleted">Depleted</option><option value="inactive">Inactive</option>
                        </select>
                    </label>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $giftcards->total() }} {{ \Illuminate\Support\Str::plural('gift card', $giftcards->total()) }}</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500 dark:bg-slate-950/60 dark:text-slate-400">
                        <tr>
                            <th scope="col" class="px-4 py-3 sm:px-5">Gift card</th>
                            <th scope="col" class="px-4 py-3">Customer</th>
                            <th scope="col" class="px-4 py-3 text-right">Remaining</th>
                            <th scope="col" class="px-4 py-3 text-right">Issued value</th>
                            <th scope="col" class="px-4 py-3">Expires</th>
                            <th scope="col" class="px-4 py-3">Status</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($giftcards as $giftcard)
                            @php
                                $isExpired = $giftcard->expires_at && $giftcard->expires_at->isPast();
                                $isDepleted = $giftcard->balance->isZero();
                                $state = ! $giftcard->is_active ? 'Inactive' : ($isExpired ? 'Expired' : ($isDepleted ? 'Depleted' : 'Available'));
                                $stateClass = match ($state) {
                                    'Available' => 'bg-emerald-50 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300',
                                    'Expired' => 'bg-amber-50 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300',
                                    'Depleted' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
                                    default => 'bg-rose-50 text-rose-800 dark:bg-rose-500/10 dark:text-rose-300',
                                };
                            @endphp
                            <tr class="transition hover:bg-slate-50/70 dark:hover:bg-slate-800/30">
                                <td class="px-4 py-3.5 sm:px-5"><a href="{{ route('giftcards.edit', $giftcard) }}" class="font-mono font-semibold text-slate-900 decoration-emerald-600 underline-offset-2 hover:text-emerald-700 hover:underline dark:text-white dark:hover:text-emerald-300">{{ $giftcard->number }}</a><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $giftcard->currency }} · Issued {{ $giftcard->created_at->format('M j, Y') }}</p></td>
                                <td class="px-4 py-3.5"><span class="block font-medium text-slate-800 dark:text-slate-200">{{ $giftcard->customer?->company_name ?: $giftcard->customer?->person?->full_name ?: 'Unassigned' }}</span>@if ($giftcard->customer?->person?->email)<span class="mt-0.5 block text-xs text-slate-500 dark:text-slate-400">{{ $giftcard->customer->person->email }}</span>@endif</td>
                                <td class="px-4 py-3.5 text-right font-semibold tabular-nums text-slate-900 dark:text-white">{{ $giftcard->balance }}</td>
                                <td class="px-4 py-3.5 text-right tabular-nums text-slate-600 dark:text-slate-400">{{ $giftcard->initial_value }}</td>
                                <td class="px-4 py-3.5 whitespace-nowrap text-xs text-slate-600 dark:text-slate-400">{{ $giftcard->expires_at?->format('M j, Y') ?? 'Never' }}</td>
                                <td class="px-4 py-3.5"><span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $stateClass }}">{{ $state }}</span></td>
                                <td class="whitespace-nowrap px-4 py-3.5 text-right"><a href="{{ route('giftcards.edit', $giftcard) }}" class="inline-flex h-9 items-center rounded-md border border-slate-300 px-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Manage</a>
                                    @can('delete', $giftcard)
                                        @if ($giftcard->balance->isEqualTo($giftcard->initial_value))
                                            <button type="button" wire:click="delete({{ $giftcard->id }})" wire:confirm="Delete gift card {{ $giftcard->number }}?" aria-label="Delete gift card {{ $giftcard->number }}" class="ml-1 inline-grid h-9 w-9 place-items-center rounded-md text-slate-400 transition hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-950/40 dark:hover:text-rose-300"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12m-10 0l1 13h6l1-13M9 7V4h6v3" /></svg></button>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-5 py-14 text-center">
                                <div class="mx-auto grid h-11 w-11 place-items-center rounded-md bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7h18v13H3zM7 7V4h10v3M7 11h10" /></svg></div>
                                <p class="mt-3 text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $search !== '' || $status !== '' ? 'No matching gift cards' : 'No gift cards issued' }}</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $search !== '' || $status !== '' ? 'Try changing the search or status filter.' : 'Issue a gift card to create a balance customers can use at checkout.' }}</p>
                                @if ($search === '' && $status === '')
                                    @can('create', \App\Domain\Giftcards\Models\Giftcard::class)
                                        <a href="{{ route('giftcards.create') }}" class="mt-4 inline-flex h-9 items-center gap-1.5 rounded-md bg-emerald-700 px-3.5 text-sm font-semibold text-white hover:bg-emerald-800 dark:bg-emerald-600 dark:hover:bg-emerald-500"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>Issue gift card</a>
                                    @endcan
                                @endif
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($giftcards->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $giftcards->links() }}</div>
            @endif
        </section>
    </div>
</div>
