<div>
    @section('title', 'Expenses')

    <div class="mx-auto max-w-7xl space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-medium text-emerald-700 dark:text-emerald-400">Finance</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 dark:text-white">Expenses</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Review operating costs, taxes, suppliers, and payment references.</p>
            </div>
            @can('create', \App\Domain\Finance\Models\Expense::class)
                <a href="{{ route('expenses.create') }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-emerald-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 dark:bg-emerald-600 dark:hover:bg-emerald-500"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>Record expense</a>
            @endcan
        </header>

        @if (session('status'))<div role="status" class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">{{ session('status') }}</div>@endif

        <section aria-label="Expense summary" class="grid gap-px overflow-hidden rounded-lg border border-slate-200 bg-slate-200 sm:grid-cols-3 dark:border-slate-800 dark:bg-slate-800">
            <div class="bg-white px-5 py-4 dark:bg-slate-900"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Expense records</p><p class="mt-1 text-2xl font-bold tabular-nums text-slate-950 dark:text-white">{{ number_format($stats['total']) }}</p></div>
            <div class="bg-white px-5 py-4 dark:bg-slate-900"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Recorded this month</p><p class="mt-1 text-2xl font-bold tabular-nums text-sky-700 dark:text-sky-300">{{ number_format($stats['this_month']) }}</p></div>
            <div class="bg-white px-5 py-4 dark:bg-slate-900"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Categories used</p><p class="mt-1 text-2xl font-bold tabular-nums text-emerald-700 dark:text-emerald-300">{{ number_format($stats['categories']) }}</p></div>
        </section>

        @if ($monthlyTotals->isNotEmpty())
            <section class="flex flex-col gap-2 rounded-lg border border-slate-200 bg-white px-4 py-3 sm:flex-row sm:items-center dark:border-slate-800 dark:bg-slate-900">
                <p class="shrink-0 text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Spend this month</p>
                <div class="flex flex-wrap gap-x-5 gap-y-1">
                    @foreach ($monthlyTotals as $monthlyTotal)
                        <p class="text-sm font-semibold tabular-nums text-slate-900 dark:text-white">{{ $monthlyTotal->currency }} {{ number_format((float) $monthlyTotal->total_amount, 2, '.', ',') }}</p>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                <label class="relative block sm:w-[26rem]"><span class="sr-only">Search expenses</span><svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-4-4"/></svg><input wire:model.live.debounce.300ms="search" type="search" placeholder="Reference, description, category, or supplier" class="h-10 w-full rounded-md border border-slate-300 bg-white pl-9 pr-3 text-sm text-slate-950 outline-none transition placeholder:text-slate-400 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $expenses->total() }} {{ \Illuminate\Support\Str::plural('expense', $expenses->total()) }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500 dark:bg-slate-950/60 dark:text-slate-400"><tr><th class="px-4 py-3 sm:px-5">Expense</th><th class="px-4 py-3">Category</th><th class="px-4 py-3">Supplier</th><th class="px-4 py-3 text-right">Amount</th><th class="px-4 py-3">Reference</th><th class="px-4 py-3"><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($expenses as $expense)
                            <tr class="transition hover:bg-slate-50/70 dark:hover:bg-slate-800/30">
                                <td class="px-4 py-3.5 sm:px-5"><span class="block font-semibold text-slate-900 dark:text-white">{{ $expense->description ?: 'Expense' }}</span><span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">{{ $expense->spent_on->format('M j, Y') }} · {{ $expense->currency }}</span></td>
                                <td class="px-4 py-3.5 text-slate-700 dark:text-slate-300">{{ $expense->category?->name ?? 'Category removed' }}</td>
                                <td class="px-4 py-3.5 text-slate-600 dark:text-slate-400">{{ $expense->supplier?->company_name ?? '—' }}</td>
                                <td class="px-4 py-3.5 text-right"><span class="block font-semibold tabular-nums text-slate-900 dark:text-white">{{ $expense->amount }}</span>@if (! $expense->tax_amount->isZero())<span class="mt-1 block text-xs tabular-nums text-slate-500 dark:text-slate-400">Tax {{ $expense->tax_amount }}</span>@endif</td>
                                <td class="px-4 py-3.5 font-mono text-xs text-slate-600 dark:text-slate-400">{{ $expense->reference ?: '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3.5 text-right">@can('update', $expense)<a href="{{ route('expenses.edit', $expense) }}" class="inline-flex h-9 items-center rounded-md border border-slate-300 px-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Edit</a>@endcan @can('delete', $expense)<button type="button" wire:click="delete({{ $expense->id }})" wire:confirm="Delete this expense record?" aria-label="Delete expense" title="Delete expense" class="ml-1 inline-grid h-9 w-9 place-items-center rounded-md text-slate-400 transition hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-950/40 dark:hover:text-rose-300"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12m-10 0l1 13h6l1-13M9 7V4h6v3"/></svg></button>@endcan</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-5 py-14 text-center"><div class="mx-auto grid h-11 w-11 place-items-center rounded-md bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M7 4h10l3 3v13H4V4h3zm2 0v5h6V4M8 14h8m-8 3h6"/></svg></div><p class="mt-3 text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $search !== '' ? 'No matching expenses' : 'No expenses recorded' }}</p><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $search !== '' ? 'Try another reference, description, category, or supplier.' : 'Record an expense to start tracking operating costs.' }}</p>@if ($search === '')@can('create', \App\Domain\Finance\Models\Expense::class)<a href="{{ route('expenses.create') }}" class="mt-4 inline-flex h-9 items-center gap-1.5 rounded-md bg-emerald-700 px-3.5 text-sm font-semibold text-white hover:bg-emerald-800 dark:bg-emerald-600 dark:hover:bg-emerald-500"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>Record expense</a>@endcan @endif</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($expenses->hasPages())<div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $expenses->links() }}</div>@endif
        </section>
    </div>
</div>
