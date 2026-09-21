<div>
    @section('title', 'Stock Counts')

    <div class="mx-auto max-w-7xl space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-medium text-emerald-700 dark:text-emerald-400">Inventory</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 dark:text-white">Stock counts</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Track physical counts from setup through approval.</p>
            </div>
            @can('create', \App\Domain\Inventory\Models\StockCount::class)
                <a href="{{ route('stock-counts.create') }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-emerald-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 dark:bg-emerald-600 dark:hover:bg-emerald-500">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
                    New stock count
                </a>
            @endcan
        </header>

        @if (session('status'))
            <div role="status" class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">{{ session('status') }}</div>
        @endif

        <section aria-label="Stock count summary" class="grid grid-cols-2 divide-x divide-y divide-slate-200 overflow-hidden rounded-lg border border-slate-200 bg-white sm:grid-cols-4 sm:divide-y-0 dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
            <div class="px-5 py-4"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">All counts</p><p class="mt-1 text-2xl font-bold tabular-nums text-slate-950 dark:text-white">{{ number_format($stats['total']) }}</p></div>
            <div class="px-5 py-4"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">In progress</p><p class="mt-1 text-2xl font-bold tabular-nums text-sky-700 dark:text-sky-300">{{ number_format($stats['open']) }}</p></div>
            <div class="px-5 py-4"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Awaiting review</p><p class="mt-1 text-2xl font-bold tabular-nums text-amber-700 dark:text-amber-300">{{ number_format($stats['review']) }}</p></div>
            <div class="px-5 py-4"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Approved</p><p class="mt-1 text-2xl font-bold tabular-nums text-emerald-700 dark:text-emerald-300">{{ number_format($stats['approved']) }}</p></div>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-3 border-b border-slate-200 p-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex flex-col gap-2 sm:flex-row">
                    <label class="relative block sm:w-80">
                        <span class="sr-only">Search stock counts</span>
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7" /><path stroke-linecap="round" d="m20 20-4-4" /></svg>
                        <input wire:model.live.debounce.300ms="search" type="search" placeholder="Search reference or location" class="h-10 w-full rounded-md border border-slate-300 bg-white pl-9 pr-3 text-sm text-slate-950 outline-none transition placeholder:text-slate-400 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    </label>
                    <label>
                        <span class="sr-only">Filter by count status</span>
                        <select wire:model.live="status" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-700 outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 sm:w-48">
                            <option value="">All statuses</option>
                            <option value="draft">Draft</option>
                            <option value="counting">Counting</option>
                            <option value="review">In review</option>
                            <option value="approved">Approved</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </label>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $stockCounts->total() }} {{ \Illuminate\Support\Str::plural('count', $stockCounts->total()) }}</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500 dark:bg-slate-950/60 dark:text-slate-400">
                        <tr>
                            <th scope="col" class="px-4 py-3 sm:px-5">Count</th>
                            <th scope="col" class="px-4 py-3">Location</th>
                            <th scope="col" class="px-4 py-3">Progress</th>
                            <th scope="col" class="px-4 py-3">Status</th>
                            <th scope="col" class="px-4 py-3">Opened</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">Action</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($stockCounts as $stockCount)
                            @php
                                $progress = $stockCount->lines_count > 0 ? (int) round(($stockCount->counted_lines_count / $stockCount->lines_count) * 100) : 0;
                                $statusStyles = match ($stockCount->status) {
                                    'counting' => 'bg-sky-50 text-sky-800 dark:bg-sky-500/10 dark:text-sky-300',
                                    'review' => 'bg-amber-50 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300',
                                    'approved' => 'bg-emerald-50 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300',
                                    'cancelled' => 'bg-rose-50 text-rose-800 dark:bg-rose-500/10 dark:text-rose-300',
                                    default => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
                                };
                                $actionLabel = match ($stockCount->status) {
                                    'draft' => 'Set up',
                                    'counting' => 'Continue',
                                    'review' => 'Review',
                                    'approved' => 'View count',
                                    default => 'View',
                                };
                            @endphp
                            <tr class="transition hover:bg-slate-50/70 dark:hover:bg-slate-800/30">
                                <td class="px-4 py-3.5 sm:px-5">
                                    <a href="{{ route('stock-counts.edit', $stockCount) }}" class="font-semibold text-slate-900 decoration-emerald-600 underline-offset-2 hover:text-emerald-700 hover:underline dark:text-white dark:hover:text-emerald-300">{{ $stockCount->reference }}</a>
                                    @if ($stockCount->is_blind)<span class="ml-1.5 rounded-md bg-violet-50 px-1.5 py-0.5 text-[11px] font-semibold text-violet-800 dark:bg-violet-500/10 dark:text-violet-300">Blind</span>@endif
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ number_format($stockCount->lines_count) }} {{ \Illuminate\Support\Str::plural('item', $stockCount->lines_count) }}</p>
                                </td>
                                <td class="px-4 py-3.5 text-slate-700 dark:text-slate-300">{{ $stockCount->stockLocation?->name ?? 'Location removed' }}</td>
                                <td class="px-4 py-3.5">
                                    @if ($stockCount->lines_count > 0)
                                        <div class="flex items-center gap-2.5">
                                            <div class="h-1.5 w-24 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"><div class="h-full rounded-full {{ $progress === 100 ? 'bg-emerald-500' : 'bg-sky-500' }}" style="width: {{ $progress }}%"></div></div>
                                            <span class="whitespace-nowrap text-xs tabular-nums text-slate-600 dark:text-slate-400">{{ number_format($stockCount->counted_lines_count) }} / {{ number_format($stockCount->lines_count) }}</span>
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-400">Lines not generated</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5"><span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusStyles }}">{{ ucfirst($stockCount->status) }}</span></td>
                                <td class="whitespace-nowrap px-4 py-3.5 text-xs text-slate-600 dark:text-slate-400">{{ $stockCount->opened_at?->timezone(config('app.timezone'))->format('M j, Y · g:i A') ?? '—' }}</td>
                                <td class="px-4 py-3.5 text-right"><a href="{{ route('stock-counts.edit', $stockCount) }}" class="inline-flex h-9 items-center rounded-md border border-slate-300 px-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">{{ $actionLabel }}</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-14 text-center">
                                <div class="mx-auto grid h-11 w-11 place-items-center rounded-md bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M7 5h10M7 9h10M7 13h6M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z" /></svg></div>
                                <p class="mt-3 text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $search !== '' || $status !== '' ? 'No matching stock counts' : 'No stock counts yet' }}</p>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $search !== '' || $status !== '' ? 'Try changing your search or status filter.' : 'Start a physical count to reconcile inventory at a location.' }}</p>
                                @if ($search === '' && $status === '')
                                    @can('create', \App\Domain\Inventory\Models\StockCount::class)
                                        <a href="{{ route('stock-counts.create') }}" class="mt-4 inline-flex h-9 items-center gap-1.5 rounded-md bg-emerald-700 px-3.5 text-sm font-semibold text-white hover:bg-emerald-800 dark:bg-emerald-600 dark:hover:bg-emerald-500"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>Start a count</a>
                                    @endcan
                                @endif
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($stockCounts->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $stockCounts->links() }}</div>
            @endif
        </section>
    </div>
</div>
