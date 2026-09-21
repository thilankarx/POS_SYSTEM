@props(['label', 'active' => false, 'icon' => null])

<div x-data="{ open: {{ $active ? 'true' : 'false' }} }">
    <button
        type="button"
        @click="
            if (sidebarCollapsed && ! mobileNavOpen) {
                sidebarCollapsed = false;
                localStorage.setItem('sidebarCollapsed', 'false');
                open = true;
            } else {
                open = ! open;
            }
        "
        :aria-expanded="open"
        title="{{ $label }}"
        class="group flex min-h-10 w-full items-center gap-3 rounded-md px-3 py-2.5 text-left text-sm font-medium transition-colors {{ $active ? 'text-slate-950 dark:text-white' : 'text-slate-600 dark:text-slate-400' }} hover:bg-slate-100 hover:text-slate-950 dark:hover:bg-slate-800 dark:hover:text-white"
    >
        @if ($icon)
            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-md {{ $active ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'text-slate-500 group-hover:text-slate-800 dark:text-slate-500 dark:group-hover:text-slate-200' }}">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}" />
                </svg>
            </span>
        @endif
        <span class="flex-1" :class="sidebarCollapsed && 'lg:hidden'">{{ $label }}</span>
        @if ($active)
            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" :class="sidebarCollapsed && 'lg:hidden'" aria-hidden="true"></span>
        @endif
        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 shrink-0 text-slate-400 transition-transform duration-200 dark:text-slate-500" :class="{ 'rotate-90': open, 'lg:hidden': sidebarCollapsed }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
        </svg>
    </button>
    <div x-show="open && (! sidebarCollapsed || mobileNavOpen)" x-collapse class="ml-6 border-l border-slate-200 py-1 pl-2 dark:border-slate-800">
        <div class="space-y-0.5">
            {{ $slot }}
        </div>
    </div>
</div>
