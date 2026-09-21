<div>
    @section('title', 'Promotions')

    <div class="mx-auto max-w-7xl space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-medium text-emerald-700 dark:text-emerald-400">Marketing</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 dark:text-white">Promotions</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Review offers, schedules, and redemption limits.</p>
            </div>
            @can('create', \App\Domain\Promotions\Models\Promotion::class)
                <a href="{{ route('promotions.create') }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-emerald-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 dark:bg-emerald-600 dark:hover:bg-emerald-500">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
                    New promotion
                </a>
            @endcan
        </header>

        @if (session('status'))
            <div role="status" class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">{{ session('status') }}</div>
        @endif

        <section aria-label="Promotion totals" class="grid grid-cols-1 divide-y divide-slate-200 rounded-lg border border-slate-200 bg-white sm:grid-cols-3 sm:divide-x sm:divide-y-0 dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
            <div class="px-5 py-4"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">All promotions</p><p class="mt-1 text-2xl font-bold tabular-nums text-slate-950 dark:text-white">{{ number_format($stats['total']) }}</p></div>
            <div class="px-5 py-4"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Available now</p><p class="mt-1 text-2xl font-bold tabular-nums text-emerald-700 dark:text-emerald-400">{{ number_format($stats['available']) }}</p></div>
            <div class="px-5 py-4"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Inactive</p><p class="mt-1 text-2xl font-bold tabular-nums text-slate-700 dark:text-slate-200">{{ number_format($stats['inactive']) }}</p></div>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-3 border-b border-slate-200 p-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-col gap-2 sm:flex-row">
                    <label class="relative block sm:w-80">
                        <span class="sr-only">Search promotions</span>
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7" /><path stroke-linecap="round" d="m20 20-4-4" /></svg>
                        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search name or code" class="h-10 w-full rounded-md border border-slate-300 bg-white pl-9 pr-3 text-sm text-slate-950 outline-none transition placeholder:text-slate-400 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    </label>
                    <label>
                        <span class="sr-only">Filter by status</span>
                        <select wire:model.live="active" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-700 outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 sm:w-44">
                            <option value="">Any status</option>
                            <option value="1">Enabled</option>
                            <option value="0">Disabled</option>
                        </select>
                    </label>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $promotions->total() }} {{ \Illuminate\Support\Str::plural('promotion', $promotions->total()) }}</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500 dark:bg-slate-950/60 dark:text-slate-400">
                        <tr>
                            <th scope="col" class="px-4 py-3 sm:px-5">Promotion</th>
                            <th scope="col" class="px-4 py-3">Reward</th>
                            <th scope="col" class="px-4 py-3">Schedule</th>
                            <th scope="col" class="px-4 py-3">Redemptions</th>
                            <th scope="col" class="px-4 py-3">Status</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($promotions as $promotion)
                            @php
                                $isWithinDates = (! $promotion->starts_at || $promotion->starts_at->lte(now()))
                                    && (! $promotion->ends_at || $promotion->ends_at->gte(now()));
                                $isAvailable = $promotion->is_active && $isWithinDates;
                                $rewardLabel = match ($promotion->reward_type) {
                                    \App\Domain\Promotions\Models\Promotion::REWARD_PERCENT_OFF => $promotion->reward_value->getAmount().'% off',
                                    \App\Domain\Promotions\Models\Promotion::REWARD_AMOUNT_OFF => (string) $promotion->reward_value.' off',
                                    \App\Domain\Promotions\Models\Promotion::REWARD_FIXED_PRICE => 'Fixed price: '.(string) $promotion->reward_value,
                                    \App\Domain\Promotions\Models\Promotion::REWARD_BOGO => 'Buy one, get one',
                                    \App\Domain\Promotions\Models\Promotion::REWARD_FREE_ITEM => 'Free item',
                                    default => str($promotion->reward_type)->replace('_', ' ')->headline(),
                                };
                            @endphp
                            <tr class="align-top transition hover:bg-slate-50/70 dark:hover:bg-slate-800/30">
                                <td class="px-4 py-3.5 sm:px-5">
                                    <a href="{{ route('promotions.edit', $promotion) }}" class="font-semibold text-slate-900 decoration-emerald-600 underline-offset-2 hover:text-emerald-700 hover:underline dark:text-white dark:hover:text-emerald-300">{{ $promotion->name }}</a>
                                    <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500 dark:text-slate-400">
                                        @if ($promotion->code)<span class="font-mono">{{ $promotion->code }}</span><span aria-hidden="true">·</span>@endif
                                        <span>{{ $promotion->conditions_count }} {{ \Illuminate\Support\Str::plural('condition', $promotion->conditions_count) }}</span>
                                        @if ($promotion->requires_coupon)<span aria-hidden="true">·</span><span>Coupon required</span>@endif
                                    </div>
                                </td>
                                <td class="px-4 py-3.5">
                                    <span class="font-medium text-slate-800 dark:text-slate-200">{{ $rewardLabel }}</span>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Priority {{ $promotion->priority }}</p>
                                </td>
                                <td class="px-4 py-3.5 text-xs text-slate-600 dark:text-slate-400">
                                    @if ($promotion->starts_at || $promotion->ends_at)
                                        @if ($promotion->starts_at)<p>From {{ $promotion->starts_at->timezone(config('pos.timezone'))->format('M j, Y') }}</p>@endif
                                        @if ($promotion->ends_at)<p @class(['mt-1', 'text-rose-600 dark:text-rose-400' => $promotion->ends_at->lt(now())])>Until {{ $promotion->ends_at->timezone(config('pos.timezone'))->format('M j, Y') }}</p>@endif
                                    @else
                                        <span class="text-slate-400">No date limit</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5">
                                    <p class="font-medium tabular-nums text-slate-800 dark:text-slate-200">{{ number_format($promotion->redemption_count) }}<span class="font-normal text-slate-500"> / {{ $promotion->max_redemptions ? number_format($promotion->max_redemptions) : '∞' }}</span></p>
                                    @if ($promotion->max_redemptions)
                                        <div class="mt-2 h-1.5 w-24 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"><div class="h-full rounded-full {{ $promotion->redemption_count >= $promotion->max_redemptions ? 'bg-rose-500' : 'bg-emerald-500' }}" style="width: {{ min(100, ($promotion->redemption_count / $promotion->max_redemptions) * 100) }}%"></div></div>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5">
                                    <span @class([
                                        'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold',
                                        'bg-emerald-50 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300' => $isAvailable,
                                        'bg-amber-50 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300' => $promotion->is_active && ! $isWithinDates,
                                        'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => ! $promotion->is_active,
                                    ])>
                                        <span @class(['h-1.5 w-1.5 rounded-full', 'bg-emerald-500' => $isAvailable, 'bg-amber-500' => $promotion->is_active && ! $isWithinDates, 'bg-slate-400' => ! $promotion->is_active])></span>
                                        {{ ! $promotion->is_active ? 'Disabled' : ($isAvailable ? 'Available' : ($promotion->starts_at && $promotion->starts_at->gt(now()) ? 'Scheduled' : 'Expired')) }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3.5 text-right">
                                    <a href="{{ route('promotions.edit', $promotion) }}" class="inline-flex h-9 items-center rounded-md border border-slate-300 px-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Manage</a>
                                    @can('delete', $promotion)
                                        <button type="button" wire:click="delete({{ $promotion->id }})" wire:confirm="Delete '{{ $promotion->name }}'? This action cannot be undone." aria-label="Delete {{ $promotion->name }}" title="Delete promotion" class="ml-1 inline-grid h-9 w-9 place-items-center rounded-md text-slate-400 transition hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-950/40 dark:hover:text-rose-300">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12m-10 0l1 13h6l1-13M9 7V4h6v3" /></svg>
                                        </button>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-14 text-center">
                                <div class="mx-auto grid h-11 w-11 place-items-center rounded-md bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M7 7h10M7 12h10M7 17h6M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z" /></svg></div>
                                <p class="mt-3 text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $search !== '' || $active !== '' ? 'No matching promotions' : 'No promotions created' }}</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $search !== '' || $active !== '' ? 'Try changing your search or status filter.' : 'Create a promotion to start managing offers and discounts.' }}</p>
                                @if ($search === '' && $active === '')
                                    @can('create', \App\Domain\Promotions\Models\Promotion::class)
                                        <a href="{{ route('promotions.create') }}" class="mt-4 inline-flex h-9 items-center gap-1.5 rounded-md bg-emerald-700 px-3.5 text-sm font-semibold text-white hover:bg-emerald-800 dark:bg-emerald-600 dark:hover:bg-emerald-500"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>Create promotion</a>
                                    @endcan
                                @endif
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($promotions->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $promotions->links() }}</div>
            @endif
        </section>
    </div>
</div>
