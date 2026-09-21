<div>
    @section('title', 'Audit Log')

    @php
        $defaultFrom = now()->subDays(30)->toDateString();
        $defaultTo = now()->toDateString();
        $hasFilters = $from !== $defaultFrom || $to !== $defaultTo || $causerId !== null || $event !== null;
    @endphp

    <div class="mx-auto max-w-7xl space-y-5">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-400">Security and accountability</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-950 dark:text-white">Audit log</h1>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Review recorded changes and system events by date, user, and event type.</p>
            </div>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ number_format($activities->total()) }} {{ \Illuminate\Support\Str::plural('record', $activities->total()) }}</p>
        </header>

        <section aria-label="Audit filters" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-[minmax(145px,1fr)_minmax(145px,1fr)_minmax(200px,1.2fr)_minmax(170px,1fr)_auto] xl:items-end">
                <div><label for="from" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">From</label><input wire:model.live.debounce.300ms="from" id="from" type="date" max="{{ $to }}" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm focus:border-emerald-600 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white"></div>
                <div><label for="to" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">To</label><input wire:model.live.debounce.300ms="to" id="to" type="date" min="{{ $from }}" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm focus:border-emerald-600 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white"></div>
                <div><label for="causerId" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">User</label><select wire:model.live="causerId" id="causerId" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm focus:border-emerald-600 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white"><option value="">All users</option>@foreach ($causers as $causer)<option value="{{ $causer->id }}">{{ $causer->name }}</option>@endforeach</select></div>
                <div><label for="event" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Event</label><select wire:model.live="event" id="event" class="h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-900 shadow-sm focus:border-emerald-600 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white"><option value="">All events</option><option value="created">Created</option><option value="updated">Updated</option><option value="deleted">Deleted</option><option value="low_stock">Low stock</option><option value="shift_opened">Shift opened</option><option value="shift_closed">Shift closed</option></select></div>
                @if ($hasFilters)
                    <button type="button" wire:click="clearFilters" class="h-10 rounded-md px-3 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">Reset</button>
                @endif
            </div>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 dark:border-slate-800">
                <div><h2 class="text-sm font-semibold text-slate-950 dark:text-white">Activity history</h2><p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">Newest events first</p></div>
                <span wire:loading.flex class="items-center gap-2 text-xs font-medium text-slate-500 dark:text-slate-400"><span class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-slate-300 border-t-emerald-600"></span>Updating</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[980px] text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-400">
                        <tr><th scope="col" class="px-4 py-3">When</th><th scope="col" class="px-4 py-3">Event</th><th scope="col" class="px-4 py-3">Subject</th><th scope="col" class="px-4 py-3">Description</th><th scope="col" class="px-4 py-3">User</th><th scope="col" class="px-4 py-3 text-right">Changes</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($activities as $activity)
                            @php
                                $eventTone = match ($activity->event) {
                                    'created' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300',
                                    'updated' => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300',
                                    'deleted' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300',
                                    default => 'bg-amber-50 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300',
                                };
                                $hasChanges = (bool) $activity->attribute_changes?->get('attributes');
                            @endphp
                            <tr wire:key="activity-{{ $activity->id }}" class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                <td class="whitespace-nowrap px-4 py-3.5"><div class="font-medium text-slate-800 dark:text-slate-200">{{ $activity->created_at->format('d M Y') }}</div><div class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $activity->created_at->format('H:i:s') }}</div></td>
                                <td class="px-4 py-3.5"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize {{ $eventTone }}">{{ str_replace('_', ' ', $activity->event ?? 'unknown') }}</span></td>
                                <td class="max-w-[220px] truncate px-4 py-3.5 font-medium text-slate-800 dark:text-slate-200" title="{{ $this->subjectLabel($activity) }}">{{ $this->subjectLabel($activity) }}</td>
                                <td class="max-w-[300px] whitespace-normal px-4 py-3.5 leading-5 text-slate-600 dark:text-slate-300">{{ $activity->description ?: '—' }}</td>
                                <td class="whitespace-nowrap px-4 py-3.5 text-slate-700 dark:text-slate-200">{{ $activity->causer?->name ?? 'System' }}</td>
                                <td class="px-4 py-3.5 text-right">
                                    @if ($hasChanges)
                                        <button type="button" wire:click="toggle({{ $activity->id }})" aria-expanded="{{ $expandedActivityId === $activity->id ? 'true' : 'false' }}" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-slate-300 px-2.5 text-xs font-semibold text-slate-700 transition hover:bg-white dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-900">
                                            <svg class="h-3.5 w-3.5 transition-transform {{ $expandedActivityId === $activity->id ? 'rotate-90' : '' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg>
                                            {{ $expandedActivityId === $activity->id ? 'Hide' : 'View changes' }}
                                        </button>
                                    @else
                                        <span class="text-xs text-slate-400 dark:text-slate-500">No field changes</span>
                                    @endif
                                </td>
                            </tr>
                            @if ($expandedActivityId === $activity->id && $hasChanges)
                                <tr wire:key="activity-changes-{{ $activity->id }}" class="bg-slate-50/80 dark:bg-slate-950/40">
                                    <td colspan="6" class="px-4 py-4 sm:px-6">
                                        <div class="mb-2 flex items-center justify-between gap-3"><h3 class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Changed fields</h3><span class="text-xs text-slate-500 dark:text-slate-400">{{ number_format(count($activity->attribute_changes->get('attributes', []))) }} {{ \Illuminate\Support\Str::plural('field', count($activity->attribute_changes->get('attributes', []))) }}</span></div>
                                        <div class="overflow-x-auto rounded-md border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                                            <table class="w-full min-w-[560px] text-left text-sm">
                                                <thead class="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-400"><tr><th scope="col" class="px-3 py-2.5">Field</th><th scope="col" class="px-3 py-2.5">Previous value</th><th scope="col" class="px-3 py-2.5">New value</th></tr></thead>
                                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                                    @foreach ($activity->attribute_changes->get('attributes', []) as $field => $newValue)
                                                        <tr><th scope="row" class="px-3 py-2.5 font-medium text-slate-800 dark:text-slate-200">{{ str_replace('_', ' ', $field) }}</th><td class="break-all px-3 py-2.5 font-mono text-xs text-slate-500 dark:text-slate-400">{{ $this->formatChangeValue($activity->attribute_changes->get('old', [])[$field] ?? null) }}</td><td class="break-all px-3 py-2.5 font-mono text-xs text-slate-800 dark:text-slate-200">{{ $this->formatChangeValue($newValue) }}</td></tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @empty
                            <tr><td colspan="6" class="px-5 py-14 text-center"><div class="mx-auto grid h-11 w-11 place-items-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 2m6-2a9 9 0 1 1-3-6.7M21 3v6h-6"/></svg></div><p class="mt-3 text-sm font-semibold text-slate-900 dark:text-white">No activity found</p><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Try adjusting the date range or filters.</p></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($activities->hasPages())
                <div class="flex flex-col gap-3 border-t border-slate-200 px-4 py-3 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-slate-500 dark:text-slate-400">Showing {{ number_format($activities->firstItem()) }}–{{ number_format($activities->lastItem()) }} of {{ number_format($activities->total()) }}</p>
                    {{ $activities->links() }}
                </div>
            @endif
        </section>
    </div>
</div>
