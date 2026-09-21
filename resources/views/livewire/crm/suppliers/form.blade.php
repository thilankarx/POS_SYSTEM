<div>
    @section('title', $supplier ? 'Edit supplier' : 'New supplier')

    @php
        $fieldClass = 'h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-950 shadow-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white';
    @endphp

    <div class="mx-auto max-w-6xl space-y-6">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-medium text-emerald-700 dark:text-emerald-400">Purchasing</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 dark:text-white">{{ $supplier ? 'Edit supplier' : 'New supplier' }}</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ $supplier ? 'Update supplier identity, contact, and purchasing details.' : 'Set up a supplier contact and purchasing account.' }}</p>
            </div>
            <a href="{{ route('suppliers.index') }}" class="inline-flex h-9 items-center gap-1.5 self-start text-sm font-semibold text-slate-600 hover:text-emerald-700 dark:text-slate-300 dark:hover:text-emerald-300 sm:self-auto">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6M9 12h12" /></svg>
                Back to suppliers
            </a>
        </header>

        @if (session('status'))
            <div role="status" class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">{{ session('status') }}</div>
        @endif

        <form wire:submit="save" class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(280px,0.72fr)] lg:items-start">
            <div class="space-y-5">
                <section class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <header class="border-b border-slate-200 px-5 py-4 dark:border-slate-800 sm:px-6">
                        <h3 class="text-base font-semibold text-slate-950 dark:text-white">Company details</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">The supplier identity used in purchasing records.</p>
                    </header>
                    <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
                        <div class="sm:col-span-2">
                            <label for="company_name" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Company name <span class="text-rose-600">*</span></label>
                            <input wire:model="company_name" id="company_name" type="text" autocomplete="organization" required class="{{ $fieldClass }}">
                            @error('company_name') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="agency_name" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Agency or trading name <span class="font-normal text-slate-500">(optional)</span></label>
                            <input wire:model="agency_name" id="agency_name" type="text" autocomplete="off" class="{{ $fieldClass }}">
                            @error('agency_name') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="tax_number" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Tax number <span class="font-normal text-slate-500">(optional)</span></label>
                            <input wire:model="tax_number" id="tax_number" type="text" autocomplete="off" class="{{ $fieldClass }} font-mono">
                            @error('tax_number') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </section>

                <section class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <header class="border-b border-slate-200 px-5 py-4 dark:border-slate-800 sm:px-6">
                        <h3 class="text-base font-semibold text-slate-950 dark:text-white">Primary contact</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Person to contact about orders and supplier account matters.</p>
                    </header>
                    <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
                        <div>
                            <label for="first_name" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">First name <span class="text-rose-600">*</span></label>
                            <input wire:model="first_name" id="first_name" type="text" autocomplete="given-name" required class="{{ $fieldClass }}">
                            @error('first_name') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="last_name" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Last name <span class="text-rose-600">*</span></label>
                            <input wire:model="last_name" id="last_name" type="text" autocomplete="family-name" required class="{{ $fieldClass }}">
                            @error('last_name') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="email" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Email <span class="font-normal text-slate-500">(optional)</span></label>
                            <input wire:model="email" id="email" type="email" autocomplete="email" class="{{ $fieldClass }}">
                            @error('email') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="phone" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Phone <span class="font-normal text-slate-500">(optional)</span></label>
                            <input wire:model="phone" id="phone" type="tel" autocomplete="tel" class="{{ $fieldClass }}">
                            @error('phone') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </section>
            </div>

            <aside class="space-y-5 lg:sticky lg:top-20">
                <section class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <header class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                        <h3 class="text-base font-semibold text-slate-950 dark:text-white">Purchasing profile</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Supplier classification and expected delivery time.</p>
                    </header>
                    <div class="space-y-5 p-5">
                        <fieldset>
                            <legend class="mb-2 text-sm font-semibold text-slate-700 dark:text-slate-300">Supplier type <span class="text-rose-600">*</span></legend>
                            <div class="space-y-2">
                                <label class="flex cursor-pointer items-start gap-3 rounded-lg border p-3.5 transition {{ $supplier_type === 'goods' ? 'border-emerald-600 bg-emerald-50/70 dark:border-emerald-500 dark:bg-emerald-950/25' : 'border-slate-200 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800/60' }}">
                                    <input wire:model="supplier_type" type="radio" name="supplier_type" value="goods" class="mt-0.5 h-4 w-4 accent-emerald-600">
                                    <span><span class="block text-sm font-semibold text-slate-900 dark:text-white">Goods supplier</span><span class="mt-1 block text-xs leading-5 text-slate-500 dark:text-slate-400">Supplies items recorded in the product catalog.</span></span>
                                </label>
                                <label class="flex cursor-pointer items-start gap-3 rounded-lg border p-3.5 transition {{ $supplier_type === 'expense' ? 'border-sky-600 bg-sky-50/70 dark:border-sky-500 dark:bg-sky-950/25' : 'border-slate-200 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800/60' }}">
                                    <input wire:model="supplier_type" type="radio" name="supplier_type" value="expense" class="mt-0.5 h-4 w-4 accent-sky-600">
                                    <span><span class="block text-sm font-semibold text-slate-900 dark:text-white">Expense supplier</span><span class="mt-1 block text-xs leading-5 text-slate-500 dark:text-slate-400">Used for services and other operating costs.</span></span>
                                </label>
                            </div>
                            @error('supplier_type') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </fieldset>

                        <div>
                            <label for="account_number" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Supplier account number <span class="font-normal text-slate-500">(optional)</span></label>
                            <input wire:model="account_number" id="account_number" type="text" autocomplete="off" class="{{ $fieldClass }} font-mono">
                            @error('account_number') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="lead_time_days" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Typical lead time</label>
                            <div class="relative">
                                <input wire:model="lead_time_days" id="lead_time_days" type="number" min="0" step="1" class="{{ $fieldClass }} pr-16 text-right tabular-nums">
                                <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs font-medium text-slate-400">days</span>
                            </div>
                            <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">Used when planning replenishment orders.</p>
                            @error('lead_time_days') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </section>
            </aside>

            <footer class="sticky bottom-0 z-10 -mx-1 flex flex-col-reverse gap-3 border-t border-slate-200 bg-white/95 px-1 py-3 backdrop-blur sm:flex-row sm:items-center sm:justify-end lg:col-span-2 dark:border-slate-800 dark:bg-slate-950/95">
                <a href="{{ route('suppliers.index') }}" class="inline-flex h-10 items-center justify-center rounded-md px-4 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">Cancel</a>
                <button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-emerald-700 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-emerald-600 dark:hover:bg-emerald-500">
                    <svg wire:loading.remove wire:target="save" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 6M4 5h12l4 4v10H4z" /></svg>
                    <svg wire:loading wire:target="save" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z" /></svg>
                    <span wire:loading.remove wire:target="save">{{ $supplier ? 'Save supplier' : 'Create supplier' }}</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </button>
            </footer>
        </form>
    </div>
</div>
