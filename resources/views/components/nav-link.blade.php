@props(['route', 'ability' => null])

@php
    $isActive = str_ends_with($route, '.index')
        ? request()->routeIs(str($route)->beforeLast('.')->append('.*')->toString())
        : request()->routeIs($route);
@endphp

@if (Route::has($route) && (! $ability || auth()->user()?->can($ability)))
    <a
        href="{{ route($route) }}"
        @click="mobileNavOpen = false"
        @if ($isActive) aria-current="page" @endif
        class="group/link flex min-h-9 items-center gap-2 rounded-md px-3 py-2 text-sm transition-colors {{ $isActive ? 'bg-emerald-50 font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white' }}"
    >
        <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ $isActive ? 'bg-emerald-500' : 'bg-slate-300 group-hover/link:bg-slate-500 dark:bg-slate-700 dark:group-hover/link:bg-slate-500' }}"></span>
        <span class="min-w-0 flex-1">{{ $slot }}</span>
    </a>
@endif
