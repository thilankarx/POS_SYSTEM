<div>
    @section('title', 'Terminals')

    <div class="mx-auto max-w-7xl space-y-5">
        @if (session('error'))
            <div role="alert" class="flex items-start gap-3 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300">
                <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 4h.01M10.3 3.9 1.8 18.5A1.7 1.7 0 003.3 21h17.4a1.7 1.7 0 001.5-2.5L13.7 3.9a2 2 0 00-3.4 0z" /></svg>
                <p>{{ session('error') }}</p>
            </div>
        @endif
        @if (session('status'))
            <div role="status" class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">{{ session('status') }}</div>
        @endif

        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-400">Sales setup</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-950 dark:text-white">Terminals</h1>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Manage register terminals, assigned stock locations, and receipt printers.</p>
            </div>
            @can('create', \App\Domain\Sales\Models\Terminal::class)
                <a href="{{ route('terminals.create') }}" class="inline-flex h-10 items-center justify-center gap-2 self-start rounded-md bg-emerald-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 dark:bg-emerald-600 dark:hover:bg-emerald-500 sm:self-auto">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-7-7h14" /></svg>
                    New terminal
                </a>
            @endcan
        </header>

        <section aria-label="Terminal totals" class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <dl class="grid grid-cols-3 divide-x divide-slate-200 dark:divide-slate-800">
                <div class="p-3 sm:p-4"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">All terminals</dt><dd class="mt-1.5 text-xl font-bold tabular-nums text-slate-950 dark:text-white">{{ number_format($totalTerminals) }}</dd></div>
                <div class="p-3 sm:p-4"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Active</dt><dd class="mt-1.5 text-xl font-bold tabular-nums text-emerald-700 dark:text-emerald-400">{{ number_format($activeTerminals) }}</dd></div>
                <div class="p-3 sm:p-4"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Printer ready</dt><dd class="mt-1.5 text-xl font-bold tabular-nums text-slate-950 dark:text-white">{{ number_format($printerConfigured) }}</dd></div>
            </dl>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-3 border-b border-slate-200 p-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-slate-950 dark:text-white">Register terminals</h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ number_format($terminals->total()) }} {{ \Illuminate\Support\Str::plural('terminal', $terminals->total()) }}</p>
                </div>
                <div class="relative w-full sm:max-w-sm">
                    <label for="terminal-search" class="sr-only">Search terminals</label>
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m16 16 4 4"/></svg>
                    <input wire:model.live.debounce.300ms="search" id="terminal-search" type="search" autocomplete="off" placeholder="Search by terminal name or code" class="h-10 w-full rounded-md border border-slate-300 bg-white pl-9 pr-10 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    @if ($search !== '')
                        <button type="button" wire:click="$set('search', '')" aria-label="Clear search" title="Clear search" class="absolute right-2 top-1/2 grid h-7 w-7 -translate-y-1/2 place-items-center rounded text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-white"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg></button>
                    @endif
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[860px] text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-400">
                        <tr>
                            <th scope="col" class="px-4 py-3">Terminal</th>
                            <th scope="col" class="px-4 py-3">Stock location</th>
                            <th scope="col" class="px-4 py-3">Receipt printer</th>
                            <th scope="col" class="px-4 py-3">Status</th>
                            <th scope="col" class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($terminals as $terminal)
                            <tr wire:key="terminal-{{ $terminal->id }}" class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3.5"><div class="font-semibold text-slate-900 dark:text-white">{{ $terminal->name }}</div><div class="mt-0.5 font-mono text-xs text-slate-500 dark:text-slate-400">{{ $terminal->code }}</div></td>
                                <td class="px-4 py-3.5 text-slate-700 dark:text-slate-200">{{ $terminal->stockLocation?->name ?? 'No location assigned' }}</td>
                                <td class="px-4 py-3.5">
                                    @if ($terminal->hasPrinterConfigured())
                                        <div class="font-medium text-slate-800 dark:text-slate-200">{{ $terminal->receipt_printer }}</div><div class="mt-0.5 text-xs capitalize text-slate-500 dark:text-slate-400">{{ $terminal->printer_connector }} connector@if ($terminal->printer_paper_width) · {{ $terminal->printer_paper_width }} mm paper@endif</div>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-md bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-500/10 dark:text-amber-300"><span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>Not configured</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3.5"><span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ $terminal->is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}"><span class="h-1.5 w-1.5 rounded-full {{ $terminal->is_active ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>{{ $terminal->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td class="px-4 py-3.5 text-right">
                                    <div class="inline-flex items-center gap-1">
                                        @can('update', $terminal)
                                            <a href="{{ route('terminals.edit', $terminal) }}" aria-label="Edit {{ $terminal->name }}" title="Edit terminal" class="grid h-9 w-9 place-items-center rounded-md text-slate-500 transition hover:bg-slate-100 hover:text-emerald-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-emerald-300"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m16 4 4 4M4 20l4-.8L19 8a2.1 2.1 0 0 0-3-3L5 16l-1 4z"/></svg></a>
                                        @endcan
                                        @can('delete', $terminal)
                                            <button type="button" wire:click="delete({{ $terminal->id }})" wire:confirm="Delete terminal {{ $terminal->name }}?" aria-label="Delete {{ $terminal->name }}" title="Delete terminal" class="grid h-9 w-9 place-items-center rounded-md text-slate-500 transition hover:bg-rose-50 hover:text-rose-700 dark:text-slate-400 dark:hover:bg-rose-950/40 dark:hover:text-rose-300"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16m-10 4v6m4-6v6M5 7l1 14h12l1-14M9 7V4h6v3"/></svg></button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-14 text-center">
                                    <div class="mx-auto grid h-11 w-11 place-items-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18v16H3zM7 8h10M7 12h4m-4 4h7"/></svg></div>
                                    <p class="mt-3 text-sm font-semibold text-slate-900 dark:text-white">{{ $search !== '' ? 'No terminals match your search' : 'No terminals configured' }}</p>
                                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $search !== '' ? 'Try another terminal name or code.' : 'Create a terminal to configure a register and receipt printer.' }}</p>
                                    @can('create', \App\Domain\Sales\Models\Terminal::class)
                                        @if ($search === '')<a href="{{ route('terminals.create') }}" class="mt-4 inline-flex h-9 items-center rounded-md border border-slate-300 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Add first terminal</a>@endif
                                    @endcan
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($terminals->hasPages())
                <div class="flex flex-col gap-3 border-t border-slate-200 px-4 py-3 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-slate-500 dark:text-slate-400">Showing {{ number_format($terminals->firstItem()) }}–{{ number_format($terminals->lastItem()) }} of {{ number_format($terminals->total()) }}</p>
                    {{ $terminals->links() }}
                </div>
            @endif
        </section>
    </div>
</div>
