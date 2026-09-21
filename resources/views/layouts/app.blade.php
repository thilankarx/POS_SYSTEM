<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="{ theme: localStorage.getItem('theme') || 'dark' }" :class="{ 'dark': theme === 'dark' }">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Laravel') }}@hasSection('title') &mdash; @yield('title')@endif</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        <style>[x-cloak] { display: none !important; }</style>
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-200">
        <div
            class="flex min-h-screen"
            x-data="{
                mobileNavOpen: false,
                sidebarCollapsed: localStorage.getItem('sidebarCollapsed') === 'true',
                toggleSidebar() {
                    this.sidebarCollapsed = ! this.sidebarCollapsed;
                    localStorage.setItem('sidebarCollapsed', this.sidebarCollapsed);
                }
            }"
            @keydown.escape.window="mobileNavOpen = false"
            x-effect="document.body.style.overflow = mobileNavOpen ? 'hidden' : ''"
        >
            <div
                x-show="mobileNavOpen"
                x-cloak
                x-transition.opacity
                @click="mobileNavOpen = false"
                class="fixed inset-0 z-30 bg-slate-950/70 backdrop-blur-sm lg:hidden"
                aria-hidden="true"
            ></div>

            <aside
                class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full transform flex-col border-r border-slate-200 bg-white shadow-2xl transition-[width,transform] duration-200 dark:border-slate-800 dark:bg-slate-900 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0 lg:shadow-none"
                :class="{
                    '!translate-x-0': mobileNavOpen,
                    'lg:w-20': sidebarCollapsed,
                    'lg:w-64': ! sidebarCollapsed
                }"
                aria-label="Main navigation"
            >
                <div class="relative flex h-16 shrink-0 items-center justify-between border-b border-slate-200 px-4 dark:border-slate-800" :class="sidebarCollapsed && 'lg:justify-center lg:px-2'">
                    <a href="{{ route('dashboard') }}" @click="mobileNavOpen = false" class="flex min-w-0 items-center gap-3">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-md bg-emerald-600 text-white shadow-sm shadow-emerald-950/20">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 14l2 2 4-4m5-4.5V19a2 2 0 01-2 2H6a2 2 0 01-2-2V5a2 2 0 012-2h8.5M9 7h3m-3 4h2m7.5-8.5a2.121 2.121 0 013 3L17 10l-4 1 1-4 4.5-4.5z" />
                            </svg>
                        </span>
                        <span class="min-w-0" :class="sidebarCollapsed && 'lg:hidden'">
                            <span class="block truncate text-sm font-bold text-slate-950 dark:text-white">{{ config('app.name', 'Laravel') }}</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">Point of sale</span>
                        </span>
                    </a>
                    <button type="button" @click="mobileNavOpen = false" class="grid h-9 w-9 place-items-center rounded-md text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-950 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white lg:hidden" aria-label="Close navigation">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                    <button
                        type="button"
                        @click="toggleSidebar()"
                        class="absolute -right-3 top-1/2 z-10 hidden h-7 w-7 -translate-y-1/2 place-items-center rounded-full border border-slate-200 bg-white text-slate-500 shadow-sm transition-colors hover:border-emerald-400 hover:text-emerald-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400 dark:hover:border-emerald-600 dark:hover:text-emerald-300 lg:grid"
                        :aria-label="sidebarCollapsed ? 'Expand navigation' : 'Collapse navigation'"
                        :title="sidebarCollapsed ? 'Expand navigation' : 'Collapse navigation'"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 transition-transform" :class="sidebarCollapsed && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>
                </div>

                <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4" :class="sidebarCollapsed && 'lg:px-2'">
                    <a
                        href="{{ route('dashboard') }}"
                        @click="mobileNavOpen = false"
                        title="Dashboard"
                        @if (request()->routeIs('dashboard')) aria-current="page" @endif
                        class="flex min-h-10 items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium transition-colors {{ request()->routeIs('dashboard') ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white' }}"
                    >
                        <span class="grid h-7 w-7 shrink-0 place-items-center rounded-md">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 10.5L12 3l9 7.5V20a1 1 0 01-1 1h-5v-6H9v6H4a1 1 0 01-1-1v-9.5z" />
                            </svg>
                        </span>
                        <span class="flex-1" :class="sidebarCollapsed && 'lg:hidden'">Dashboard</span>
                        @if (request()->routeIs('dashboard'))
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" :class="sidebarCollapsed && 'lg:hidden'" aria-hidden="true"></span>
                        @endif
                    </a>

                    <div class="my-3 border-t border-slate-200 dark:border-slate-800"></div>

                    <x-nav-group label="Sales" icon="M3 3h2l.4 2M7 13h10l3-8H6.4M7 13L5.4 5M7 13l-2.3 4.6a1 1 0 00.9 1.4H17M9 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z" :active="request()->routeIs('shift.*', 'sales.*', 'pos')">
                        <x-nav-link route="pos" ability="sales.create">Register</x-nav-link>
                        <x-nav-link route="shift.manage">Shift</x-nav-link>
                        <x-nav-link route="shift.history" ability="shifts.view_all">Shift History</x-nav-link>
                        <x-nav-link route="sales.index" ability="sales.view">Sales</x-nav-link>
                    </x-nav-group>

                    @canany(['items.view'])
                        <x-nav-group label="Catalog" icon="M20.59 13.41L11 3.83A2 2 0 009.53 3.17L4 3a1 1 0 00-1 1l.17 5.53a2 2 0 00.66 1.47l9.58 9.58a2 2 0 002.83 0l4.35-4.35a2 2 0 000-2.83zM7 7h.01" :active="request()->routeIs('items.*', 'categories.*', 'item-kits.*', 'attribute-definitions.*')">
                            <x-nav-link route="items.index" ability="items.view">Items</x-nav-link>
                            <x-nav-link route="categories.index" ability="items.view">Categories</x-nav-link>
                            <x-nav-link route="item-kits.index" ability="item_kits.view">Item Kits</x-nav-link>
                            <x-nav-link route="attribute-definitions.index" ability="attributes.manage">Attributes</x-nav-link>
                        </x-nav-group>
                    @endcanany

                    @canany(['customers.view', 'suppliers.view'])
                        <x-nav-group label="Customers & Suppliers" icon="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4zm6 0a4 4 0 10-1.13-7.86" :active="request()->routeIs('customers.*', 'suppliers.*')">
                            <x-nav-link route="customers.index" ability="customers.view">Customers</x-nav-link>
                            <x-nav-link route="suppliers.index" ability="suppliers.view">Suppliers</x-nav-link>
                        </x-nav-group>
                    @endcanany

                    @canany(['locations.view', 'inventory.view', 'inventory.adjust', 'inventory.transfer'])
                        <x-nav-group label="Inventory" icon="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" :active="request()->routeIs('stock-locations.*', 'stock-counts.*', 'stock-adjustments.*', 'stock-transfers.*', 'serial-numbers.*')">
                            <x-nav-link route="stock-locations.index" ability="locations.view">Stock Locations</x-nav-link>
                            <x-nav-link route="stock-counts.index" ability="inventory.view">Stock Counts</x-nav-link>
                            <x-nav-link route="stock-adjustments.create" ability="inventory.adjust">Adjust Stock</x-nav-link>
                            <x-nav-link route="stock-transfers.create" ability="inventory.transfer">Transfer Stock</x-nav-link>
                            <x-nav-link route="serial-numbers.index" ability="inventory.view">Serial Numbers</x-nav-link>
                        </x-nav-group>
                    @endcanany

                    @canany(['purchasing.view', 'receivings.view'])
                        <x-nav-group label="Purchasing" icon="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" :active="request()->routeIs('purchase-orders.*', 'reorder-suggestions.*', 'supplier-invoices.*', 'receivings.*')">
                            <x-nav-link route="purchase-orders.index" ability="purchasing.view">Purchase Orders</x-nav-link>
                            <x-nav-link route="reorder-suggestions.index" ability="purchasing.view">Reorder Suggestions</x-nav-link>
                            <x-nav-link route="supplier-invoices.index" ability="purchasing.view">Supplier Invoices</x-nav-link>
                            <x-nav-link route="receivings.index" ability="receivings.view">Receivings</x-nav-link>
                        </x-nav-group>
                    @endcanany

                    @canany(['promotions.view', 'loyalty.view', 'giftcards.view'])
                        <x-nav-group label="Marketing" icon="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" :active="request()->routeIs('promotions.*', 'loyalty-packages.*', 'giftcards.*')">
                            <x-nav-link route="promotions.index" ability="promotions.view">Promotions</x-nav-link>
                            <x-nav-link route="loyalty-packages.index" ability="loyalty.view">Loyalty Packages</x-nav-link>
                            <x-nav-link route="giftcards.index" ability="giftcards.view">Gift Cards</x-nav-link>
                        </x-nav-group>
                    @endcanany

                    @canany(['expenses.view'])
                        <x-nav-group label="Finance" icon="M12 8c-1.5 0-2.5.7-2.5 1.8 0 2.5 4 1.7 4 4.2 0 1.1-1 1.8-2.5 1.8S8.5 15.1 8.5 14M12 6v1.2M12 16.8V18M3 12a9 9 0 1018 0 9 9 0 00-18 0z" :active="request()->routeIs('expenses.*', 'expense-categories.*')">
                            <x-nav-link route="expenses.index" ability="expenses.view">Expenses</x-nav-link>
                            <x-nav-link route="expense-categories.index" ability="expenses.view">Expense Categories</x-nav-link>
                        </x-nav-group>
                    @endcanany

                    @can('reports.view')
                        <x-nav-group label="Reports" icon="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" :active="request()->routeIs('reports.*')">
                            <x-nav-link route="reports.sales" ability="reports.sales">Sales</x-nav-link>
                            <x-nav-link route="reports.inventory" ability="reports.inventory">Inventory</x-nav-link>
                            <x-nav-link route="reports.shifts" ability="reports.shifts">Shift/Cash</x-nav-link>
                            <x-nav-link route="reports.items" ability="reports.items">Items</x-nav-link>
                            <x-nav-link route="reports.categories" ability="reports.categories">Categories</x-nav-link>
                            <x-nav-link route="reports.suppliers" ability="reports.suppliers">Suppliers</x-nav-link>
                            <x-nav-link route="reports.receivings" ability="reports.receivings">Receivings</x-nav-link>
                            <x-nav-link route="reports.customers" ability="reports.customers">Customers</x-nav-link>
                            <x-nav-link route="reports.payments" ability="reports.payments">Payments</x-nav-link>
                            <x-nav-link route="reports.taxes" ability="reports.taxes">Taxes</x-nav-link>
                            <x-nav-link route="reports.commissions" ability="reports.employees">Commissions</x-nav-link>
                        </x-nav-group>
                    @endcan

                    @canany(['users.view', 'terminals.manage', 'tables.manage', 'taxes.manage', 'config.manage', 'audit.view'])
                        <x-nav-group label="Settings" icon="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065zM15 12a3 3 0 11-6 0 3 3 0 016 0z" :active="request()->routeIs('users.*', 'terminals.*', 'dinner-tables.*', 'tax-categories.*', 'settings.*', 'audit.*')">
                            <x-nav-link route="users.index" ability="users.view">Users</x-nav-link>
                            <x-nav-link route="terminals.index" ability="terminals.manage">Terminals</x-nav-link>
                            @if (app(\App\Settings\BusinessProfileSettings::class)->business_type === \App\Settings\BusinessProfileSettings::BUSINESS_TYPE_RESTAURANT)
                                <x-nav-link route="dinner-tables.index" ability="tables.manage">Dinner Tables</x-nav-link>
                            @endif
                            <x-nav-link route="tax-categories.index" ability="taxes.manage">Tax Categories</x-nav-link>
                            <x-nav-link route="settings.business-profile" ability="config.manage">Business Profile</x-nav-link>
                            <x-nav-link route="settings.numbering" ability="config.manage">Document Numbering</x-nav-link>
                            <x-nav-link route="settings.tax" ability="config.manage">Tax Defaults</x-nav-link>
                            <x-nav-link route="settings.labels" ability="config.manage">Label Printer</x-nav-link>
                            <x-nav-link route="audit.index" ability="audit.view">Audit Log</x-nav-link>
                        </x-nav-group>
                    @endcanany
                </nav>

                <div class="shrink-0 border-t border-slate-200 p-3 dark:border-slate-800 lg:hidden">
                    <div class="flex items-center gap-3 px-2 py-1">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-md bg-slate-100 text-sm font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-200">
                            {{ str(auth()->user()->name)->substr(0, 1)->upper() }}
                        </span>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold text-slate-900 dark:text-white">{{ auth()->user()->name }}</span>
                            <span class="block truncate text-xs capitalize text-slate-500 dark:text-slate-400">{{ auth()->user()->getRoleNames()->first() }}</span>
                        </span>
                    </div>
                </div>
            </aside>

            <div class="flex min-h-screen min-w-0 flex-1 flex-col">
                <header class="sticky top-0 z-20 flex h-16 shrink-0 items-center justify-between border-b border-slate-200 bg-white/90 px-4 backdrop-blur-md transition-colors dark:border-slate-800 dark:bg-slate-900/90 sm:px-6">
                    <div class="flex min-w-0 items-center gap-3">
                        <button type="button" @click="mobileNavOpen = true" class="grid h-9 w-9 shrink-0 place-items-center rounded-md text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-950 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white lg:hidden" aria-label="Open navigation">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                        </button>
                        <div class="min-w-0">
                            <p class="hidden text-xs font-medium uppercase text-slate-500 sm:block dark:text-slate-400">Workspace</p>
                            <h1 class="truncate text-sm font-semibold text-slate-950 dark:text-white">@yield('title', 'Dashboard')</h1>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-1.5 text-sm sm:gap-2">
                        <button 
                            type="button" 
                            @click="theme = (theme === 'dark' ? 'light' : 'dark'); localStorage.setItem('theme', theme)" 
                            class="grid h-9 w-9 place-items-center rounded-md text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-950 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
                            aria-label="Toggle dark mode"
                            title="Toggle color theme"
                        >
                            <svg x-show="theme === 'dark'" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                            <svg x-show="theme !== 'dark'" x-cloak xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                            </svg>
                        </button>

                        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                            <button
                                type="button"
                                @click="open = !open"
                                :aria-expanded="open"
                                aria-haspopup="menu"
                                class="flex h-10 items-center gap-2 rounded-md px-1.5 text-left transition-colors hover:bg-slate-100 dark:hover:bg-slate-800 sm:px-2"
                            >
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-md bg-emerald-600 text-xs font-bold text-white">
                                    {{ str(auth()->user()->name)->substr(0, 1)->upper() }}
                                </span>
                                <span class="hidden min-w-0 max-w-36 sm:block">
                                    <span class="block truncate text-sm font-semibold text-slate-900 dark:text-white">{{ auth()->user()->name }}</span>
                                    <span class="block truncate text-xs capitalize text-slate-500 dark:text-slate-400">{{ auth()->user()->getRoleNames()->first() }}</span>
                                </span>
                                <svg xmlns="http://www.w3.org/2000/svg" class="hidden h-4 w-4 text-slate-400 transition-transform sm:block" :class="open && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <div
                                x-show="open"
                                x-cloak
                                x-transition.origin.top.right
                                role="menu"
                                class="absolute right-0 mt-2 w-56 rounded-md border border-slate-200 bg-white p-1.5 shadow-xl dark:border-slate-700 dark:bg-slate-900"
                            >
                                <div class="border-b border-slate-200 px-3 py-2 sm:hidden dark:border-slate-800">
                                    <p class="truncate text-sm font-semibold text-slate-900 dark:text-white">{{ auth()->user()->name }}</p>
                                    <p class="truncate text-xs capitalize text-slate-500 dark:text-slate-400">{{ auth()->user()->getRoleNames()->first() }}</p>
                                </div>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" role="menuitem" class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm font-medium text-slate-700 transition-colors hover:bg-red-50 hover:text-red-700 dark:text-slate-300 dark:hover:bg-red-500/10 dark:hover:text-red-300">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1" />
                                        </svg>
                                        Log out
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </header>

                <main class="min-w-0 flex-1 p-4 sm:p-6">
                    @if (session('status'))
                        <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 shadow-sm dark:border-emerald-900/50 dark:bg-emerald-950/50 dark:text-emerald-300">
                            {{ session('status') }}
                        </div>
                    @endif

                    {{ $slot }}
                </main>
            </div>
        </div>

        @livewireScripts
    </body>
</html>
