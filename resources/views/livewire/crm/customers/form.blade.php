<div>
    @section('title', $customer ? 'Edit customer' : 'New customer')

    @php
        $fieldClass = 'h-11 w-full rounded-md border border-slate-300 bg-white px-3 text-sm text-slate-950 shadow-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white';
    @endphp

    <div class="mx-auto max-w-6xl space-y-6">
        <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-medium text-emerald-700 dark:text-emerald-400">Customer relationships</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 dark:text-white">{{ $customer ? 'Edit customer' : 'New customer' }}</h2>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">{{ $customer ? 'Update contact and account details.' : 'Add contact details and configure the customer account.' }}</p>
            </div>
            <a href="{{ route('customers.index') }}" class="inline-flex h-9 items-center gap-1.5 self-start text-sm font-semibold text-slate-600 hover:text-emerald-700 dark:text-slate-300 dark:hover:text-emerald-300 sm:self-auto">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6M9 12h12" /></svg>
                Back to customers
            </a>
        </header>

        @if (session('status'))
            <div role="status" class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300">{{ session('status') }}</div>
        @endif

        <form wire:submit="save" class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(280px,0.72fr)] lg:items-start">
            <div class="space-y-5">
                <section class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <header class="border-b border-slate-200 px-5 py-4 dark:border-slate-800 sm:px-6">
                        <h3 class="text-base font-semibold text-slate-950 dark:text-white">Contact information</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">The primary person associated with this customer account.</p>
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
                        <div class="sm:col-span-2">
                            <label for="company_name" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Company <span class="font-normal text-slate-500">(optional)</span></label>
                            <input wire:model="company_name" id="company_name" type="text" autocomplete="organization" class="{{ $fieldClass }}">
                            @error('company_name') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </section>

                <section class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <header class="border-b border-slate-200 px-5 py-4 dark:border-slate-800 sm:px-6">
                        <h3 class="text-base font-semibold text-slate-950 dark:text-white">Account settings</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Account reference and customer-specific tax and credit settings.</p>
                    </header>
                    <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
                        <div>
                            <label for="account_number" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Account number <span class="font-normal text-slate-500">(optional)</span></label>
                            <input wire:model="account_number" id="account_number" type="text" autocomplete="off" class="{{ $fieldClass }} font-mono">
                            @error('account_number') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="credit_limit" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Credit limit <span class="text-rose-600">*</span></label>
                            <input wire:model="credit_limit" id="credit_limit" type="text" inputmode="decimal" autocomplete="off" required class="{{ $fieldClass }} text-right tabular-nums">
                            @error('credit_limit') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label for="tax_category_id" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Tax category <span class="font-normal text-slate-500">(optional)</span></label>
                            <select wire:model="tax_category_id" id="tax_category_id" class="{{ $fieldClass }}">
                                <option value="">Use default tax settings</option>
                                @foreach ($taxCategories as $taxCategory)
                                    <option value="{{ $taxCategory->id }}">{{ $taxCategory->name }}</option>
                                @endforeach
                            </select>
                            @error('tax_category_id') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                            <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">A customer tax category overrides the default when calculating tax.</p>
                        </div>
                    </div>
                </section>
            </div>

            <aside class="space-y-5 lg:sticky lg:top-20">
                <section class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <header class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                        <h3 class="text-base font-semibold text-slate-950 dark:text-white">Customer programs</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Tax treatment, marketing, and loyalty enrollment.</p>
                    </header>
                    <div class="divide-y divide-slate-100 px-5 dark:divide-slate-800">
                        <label class="flex cursor-pointer items-start justify-between gap-4 py-4">
                            <span><span class="block text-sm font-semibold text-slate-800 dark:text-slate-200">Tax exempt</span><span class="mt-1 block text-xs leading-5 text-slate-500 dark:text-slate-400">Exclude this customer from tax where applicable.</span></span>
                            <input wire:model="is_tax_exempt" type="checkbox" class="mt-0.5 h-5 w-5 shrink-0 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 dark:border-slate-700">
                        </label>
                        <label class="flex cursor-pointer items-start justify-between gap-4 py-4">
                            <span><span class="block text-sm font-semibold text-slate-800 dark:text-slate-200">Marketing consent</span><span class="mt-1 block text-xs leading-5 text-slate-500 dark:text-slate-400">Record permission to receive marketing messages.</span></span>
                            <input wire:model="marketing_consent" type="checkbox" class="mt-0.5 h-5 w-5 shrink-0 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 dark:border-slate-700">
                        </label>
                        <div class="py-4">
                            <label for="loyalty_package_id" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Loyalty package</label>
                            <select wire:model="loyalty_package_id" id="loyalty_package_id" class="{{ $fieldClass }}">
                                <option value="">Not enrolled</option>
                                @foreach ($loyaltyPackages as $loyaltyPackage)
                                    <option value="{{ $loyaltyPackage->id }}">{{ $loyaltyPackage->name }}</option>
                                @endforeach
                            </select>
                            @error('loyalty_package_id') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </section>

                @if ($customer)
                    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                        <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800">
                            <h3 class="text-base font-semibold text-slate-950 dark:text-white">Loyalty points</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Current balance <span class="font-semibold tabular-nums text-slate-800 dark:text-slate-200">{{ number_format((float) $customer->points_balance, 3, '.', ',') }}</span></p>
                        </div>
                        @can('loyalty.manage')
                            <div class="space-y-3 p-5">
                                <div>
                                    <label for="pointsAdjustment" class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-300">Adjustment</label>
                                    <input wire:model="pointsAdjustment" id="pointsAdjustment" type="text" inputmode="decimal" placeholder="e.g. 25 or -10" class="{{ $fieldClass }}">
                                    <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">Use a negative amount to deduct points.</p>
                                    @error('pointsAdjustment') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                                </div>
                                <button type="button" wire:click="adjustPoints" wire:loading.attr="disabled" wire:target="adjustPoints" class="inline-flex h-10 w-full items-center justify-center rounded-md border border-slate-300 px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:opacity-60 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                                    <span wire:loading.remove wire:target="adjustPoints">Adjust points</span><span wire:loading wire:target="adjustPoints">Adjusting…</span>
                                </button>
                            </div>
                        @endcan
                    </section>
                @endif
            </aside>

            <footer class="sticky bottom-0 z-10 -mx-1 flex flex-col-reverse gap-3 border-t border-slate-200 bg-white/95 px-1 py-3 backdrop-blur sm:flex-row sm:items-center sm:justify-end lg:col-span-2 dark:border-slate-800 dark:bg-slate-950/95">
                <a href="{{ route('customers.index') }}" class="inline-flex h-10 items-center justify-center rounded-md px-4 text-sm font-semibold text-slate-600 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">Cancel</a>
                <button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-emerald-700 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-emerald-600 dark:hover:bg-emerald-500">
                    <svg wire:loading.remove wire:target="save" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 6M4 5h12l4 4v10H4z" /></svg>
                    <svg wire:loading wire:target="save" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z" /></svg>
                    <span wire:loading.remove wire:target="save">{{ $customer ? 'Save customer' : 'Create customer' }}</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </button>
            </footer>
        </form>
    </div>
</div>
