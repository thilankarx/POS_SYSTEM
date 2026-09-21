<div>
    @section('title', 'Expense Categories')

    <div class="mx-auto max-w-7xl space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div><p class="text-sm font-medium text-emerald-700 dark:text-emerald-400">Finance</p><h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 dark:text-white">Expense categories</h2><p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Organize expenses for consistent financial reporting.</p></div>
            @can('create', \App\Domain\Finance\Models\ExpenseCategory::class)
                <a href="{{ route('expense-categories.create') }}" class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-emerald-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 dark:bg-emerald-600 dark:hover:bg-emerald-500"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>New category</a>
            @endcan
        </header>

        @if (session('status'))<div role="status" class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">{{ session('status') }}</div>@endif

        <section aria-label="Category summary" class="grid grid-cols-1 divide-y divide-slate-200 rounded-lg border border-slate-200 bg-white sm:grid-cols-3 sm:divide-x sm:divide-y-0 dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
            <div class="px-5 py-4"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Categories</p><p class="mt-1 text-2xl font-bold tabular-nums text-slate-950 dark:text-white">{{ number_format($stats['total']) }}</p></div>
            <div class="px-5 py-4"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">In use</p><p class="mt-1 text-2xl font-bold tabular-nums text-emerald-700 dark:text-emerald-300">{{ number_format($stats['used']) }}</p></div>
            <div class="px-5 py-4"><p class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Expense records</p><p class="mt-1 text-2xl font-bold tabular-nums text-sky-700 dark:text-sky-300">{{ number_format($stats['expenses']) }}</p></div>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
                <label class="relative block sm:w-80"><span class="sr-only">Search expense categories</span><svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-4-4"/></svg><input wire:model.live.debounce.300ms="search" type="search" placeholder="Search category name or code" class="h-10 w-full rounded-md border border-slate-300 bg-white pl-9 pr-3 text-sm text-slate-950 outline-none transition placeholder:text-slate-400 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white"></label>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $expenseCategories->total() }} {{ \Illuminate\Support\Str::plural('category', $expenseCategories->total()) }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500 dark:bg-slate-950/60 dark:text-slate-400"><tr><th class="px-4 py-3 sm:px-5">Category</th><th class="px-4 py-3">Code</th><th class="px-4 py-3">Description</th><th class="px-4 py-3 text-right">Expenses</th><th class="px-4 py-3"><span class="sr-only">Actions</span></th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($expenseCategories as $expenseCategory)
                            <tr class="transition hover:bg-slate-50/70 dark:hover:bg-slate-800/30">
                                <td class="px-4 py-3.5 font-semibold text-slate-900 dark:text-white sm:px-5">{{ $expenseCategory->name }}</td>
                                <td class="px-4 py-3.5 font-mono text-xs font-medium text-slate-600 dark:text-slate-400">{{ $expenseCategory->code }}</td>
                                <td class="max-w-lg px-4 py-3.5 text-slate-600 dark:text-slate-400"><span class="line-clamp-2">{{ $expenseCategory->description ?: '—' }}</span></td>
                                <td class="px-4 py-3.5 text-right tabular-nums text-slate-700 dark:text-slate-300">{{ number_format($expenseCategory->expenses_count) }}</td>
                                <td class="whitespace-nowrap px-4 py-3.5 text-right">@can('update', $expenseCategory)<a href="{{ route('expense-categories.edit', $expenseCategory) }}" class="inline-flex h-9 items-center rounded-md border border-slate-300 px-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Edit</a>@endcan @can('delete', $expenseCategory)<button type="button" wire:click="delete({{ $expenseCategory->id }})" wire:confirm="Delete category '{{ $expenseCategory->name }}'?" aria-label="Delete {{ $expenseCategory->name }}" class="ml-1 inline-grid h-9 w-9 place-items-center rounded-md text-slate-400 transition hover:bg-rose-50 hover:text-rose-700 dark:hover:bg-rose-950/40 dark:hover:text-rose-300"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12m-10 0l1 13h6l1-13M9 7V4h6v3"/></svg></button>@endcan</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-14 text-center"><div class="mx-auto grid h-11 w-11 place-items-center rounded-md bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16M4 10h10M4 15h16M4 20h10"/></svg></div><p class="mt-3 text-sm font-semibold text-slate-800 dark:text-slate-200">{{ $search !== '' ? 'No matching categories' : 'No expense categories yet' }}</p><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $search !== '' ? 'Try another category name or code.' : 'Create categories to keep expense records organized.' }}</p>@if ($search === '')@can('create', \App\Domain\Finance\Models\ExpenseCategory::class)<a href="{{ route('expense-categories.create') }}" class="mt-4 inline-flex h-9 items-center gap-1.5 rounded-md bg-emerald-700 px-3.5 text-sm font-semibold text-white hover:bg-emerald-800 dark:bg-emerald-600 dark:hover:bg-emerald-500"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>Create category</a>@endcan @endif</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($expenseCategories->hasPages())<div class="border-t border-slate-200 px-4 py-3 dark:border-slate-800">{{ $expenseCategories->links() }}</div>@endif
        </section>
    </div>
</div>
