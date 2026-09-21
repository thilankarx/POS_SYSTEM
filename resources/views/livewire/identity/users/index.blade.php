<div>
    @section('title', 'Users')

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
                <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-400">Access management</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-950 dark:text-white">Users</h1>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Manage staff accounts, roles, and account access.</p>
            </div>
            @can('create', \App\Domain\Identity\Models\User::class)
                <a href="{{ route('users.create') }}" class="inline-flex h-10 items-center justify-center gap-2 self-start rounded-md bg-emerald-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 dark:bg-emerald-600 dark:hover:bg-emerald-500 sm:self-auto">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14m-7-7h14" /></svg>
                    New user
                </a>
            @endcan
        </header>

        <section aria-label="User totals" class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <dl class="grid grid-cols-3 divide-x divide-slate-200 dark:divide-slate-800">
                <div class="p-3 sm:p-4"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">All users</dt><dd class="mt-1.5 text-xl font-bold tabular-nums text-slate-950 dark:text-white">{{ number_format($totalUsers) }}</dd></div>
                <div class="p-3 sm:p-4"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Active</dt><dd class="mt-1.5 text-xl font-bold tabular-nums text-emerald-700 dark:text-emerald-400">{{ number_format($activeUsers) }}</dd></div>
                <div class="p-3 sm:p-4"><dt class="text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Inactive</dt><dd class="mt-1.5 text-xl font-bold tabular-nums text-slate-500 dark:text-slate-400">{{ number_format($inactiveUsers) }}</dd></div>
            </dl>
        </section>

        <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-3 border-b border-slate-200 p-4 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-slate-950 dark:text-white">Staff accounts</h2>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ number_format($users->total()) }} {{ \Illuminate\Support\Str::plural('account', $users->total()) }}</p>
                </div>
                <div class="relative w-full sm:max-w-sm">
                    <label for="user-search" class="sr-only">Search users</label>
                    <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m16 16 4 4"/></svg>
                    <input wire:model.live.debounce.300ms="search" id="user-search" type="search" autocomplete="off" placeholder="Search name, username, or email" class="h-10 w-full rounded-md border border-slate-300 bg-white pl-9 pr-10 text-sm text-slate-900 shadow-sm outline-none transition placeholder:text-slate-400 focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                    @if ($search !== '')
                        <button type="button" wire:click="$set('search', '')" aria-label="Clear search" title="Clear search" class="absolute right-2 top-1/2 grid h-7 w-7 -translate-y-1/2 place-items-center rounded text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-white"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="m6 6 12 12M18 6 6 18"/></svg></button>
                    @endif
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase text-slate-500 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-400">
                        <tr>
                            <th scope="col" class="px-4 py-3">User</th>
                            <th scope="col" class="px-4 py-3">Email</th>
                            <th scope="col" class="px-4 py-3">Role</th>
                            <th scope="col" class="px-4 py-3">Last login</th>
                            <th scope="col" class="px-4 py-3">Account</th>
                            <th scope="col" class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @forelse ($users as $user)
                            @php
                                $displayName = $user->person?->full_name ?: $user->username;
                                $initials = collect(explode(' ', $displayName))->filter()->take(2)->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
                            @endphp
                            <tr wire:key="user-{{ $user->id }}" class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3.5">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300">{{ $initials }}</span>
                                        <div class="min-w-0"><div class="truncate font-semibold text-slate-900 dark:text-white">{{ $displayName }}</div><div class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">{{ '@'.$user->username }}</div></div>
                                    </div>
                                </td>
                                <td class="max-w-xs truncate px-4 py-3.5 text-slate-600 dark:text-slate-300">{{ $user->email }}</td>
                                <td class="px-4 py-3.5"><span class="inline-flex rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:bg-slate-800 dark:text-slate-200">{{ $user->roles->first()?->name ?? 'No role' }}</span></td>
                                <td class="whitespace-nowrap px-4 py-3.5 text-slate-600 dark:text-slate-300">{{ $user->last_login_at?->format('d M Y, H:i') ?? 'Never' }}</td>
                                <td class="px-4 py-3.5"><span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ $user->is_active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}"><span class="h-1.5 w-1.5 rounded-full {{ $user->is_active ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>{{ $user->is_active ? 'Active' : 'Inactive' }}</span></td>
                                <td class="px-4 py-3.5 text-right">
                                    <div class="inline-flex items-center gap-1">
                                        @can('update', $user)
                                            <a href="{{ route('users.edit', $user) }}" aria-label="Edit {{ $user->username }}" title="Edit user" class="grid h-9 w-9 place-items-center rounded-md text-slate-500 transition hover:bg-slate-100 hover:text-emerald-700 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-emerald-300"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m16 4 4 4M4 20l4-.8L19 8a2.1 2.1 0 0 0-3-3L5 16l-1 4z"/></svg></a>
                                        @endcan
                                        @can('delete', $user)
                                            <button type="button" wire:click="delete({{ $user->id }})" wire:confirm="Delete {{ $user->username }}?" aria-label="Delete {{ $user->username }}" title="Delete user" class="grid h-9 w-9 place-items-center rounded-md text-slate-500 transition hover:bg-rose-50 hover:text-rose-700 dark:text-slate-400 dark:hover:bg-rose-950/40 dark:hover:text-rose-300"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16m-10 4v6m4-6v6M5 7l1 14h12l1-14M9 7V4h6v3"/></svg></button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-14 text-center">
                                    <div class="mx-auto grid h-11 w-11 place-items-center rounded-full bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2m12-13a4 4 0 1 1-8 0 4 4 0 0 1 8 0zm4 5a4 4 0 0 1 2 3.5V19m-2-15a4 4 0 0 1 0 7.75"/></svg></div>
                                    <p class="mt-3 text-sm font-semibold text-slate-900 dark:text-white">{{ $search !== '' ? 'No users match your search' : 'No user accounts yet' }}</p>
                                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $search !== '' ? 'Try a different name, username, or email.' : 'Create a user account to give a team member access.' }}</p>
                                    @can('create', \App\Domain\Identity\Models\User::class)
                                        @if ($search === '')<a href="{{ route('users.create') }}" class="mt-4 inline-flex h-9 items-center rounded-md border border-slate-300 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Add first user</a>@endif
                                    @endcan
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($users->hasPages())
                <div class="flex flex-col gap-3 border-t border-slate-200 px-4 py-3 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-slate-500 dark:text-slate-400">Showing {{ number_format($users->firstItem()) }}–{{ number_format($users->lastItem()) }} of {{ number_format($users->total()) }}</p>
                    {{ $users->links() }}
                </div>
            @endif
        </section>
    </div>
</div>
