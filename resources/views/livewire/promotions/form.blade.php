<div>
    @section('title', $promotion ? "Promotion: {$promotion->name}" : 'New promotion')

    @php
        $fieldClass = 'h-10 w-full rounded-md border border-slate-300 bg-white px-3 text-sm shadow-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:border-slate-700 dark:bg-slate-950';
        $statusActive = $promotion?->is_active ?? $is_active;
    @endphp

    <div class="max-w-7xl space-y-5">
        <header class="flex flex-col gap-4 border-b border-slate-200 pb-4 dark:border-slate-800 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase text-emerald-700 dark:text-emerald-400">Marketing</p>
                <div class="mt-1 flex flex-wrap items-center gap-2">
                    <h2 class="text-2xl font-bold text-slate-950 dark:text-white">{{ $promotion ? 'Edit promotion' : 'New promotion' }}</h2>
                    @if ($promotion)
                        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusActive ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $statusActive ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                            {{ $statusActive ? 'Active' : 'Inactive' }}
                        </span>
                    @endif
                </div>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Define the reward, eligibility rules, availability, and redemption limits.</p>
            </div>
            <a href="{{ route('promotions.index') }}" class="inline-flex h-10 items-center gap-2 self-start rounded-md border border-slate-300 bg-white px-3.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 sm:self-auto">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M15 19l-7-7 7-7" /></svg>
                Back to promotions
            </a>
        </header>

        @if ($promotion)
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="border-l-2 border-emerald-500 px-3 py-1"><p class="text-xs font-semibold uppercase text-slate-500">Redemptions</p><p class="mt-1 text-xl font-bold tabular-nums">{{ $promotion->redemption_count }}</p></div>
                <div class="border-l-2 border-sky-500 px-3 py-1"><p class="text-xs font-semibold uppercase text-slate-500">Overall limit</p><p class="mt-1 text-xl font-bold tabular-nums">{{ $promotion->max_redemptions ?? 'Unlimited' }}</p></div>
                <div class="border-l-2 border-amber-500 px-3 py-1"><p class="text-xs font-semibold uppercase text-slate-500">Coupons issued</p><p class="mt-1 text-xl font-bold tabular-nums">{{ $promotion->coupons->count() }}</p></div>
            </div>
        @endif

        @error('conditions')
            <div role="alert" class="flex items-start gap-2 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300">
                <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 9v3m0 4h.01M4 20h16L12 4 4 20z" /></svg>{{ $message }}
            </div>
        @enderror

        <form wire:submit="save" class="space-y-4">
            <div class="grid gap-4 xl:grid-cols-3 xl:items-start">
                <div class="space-y-4 xl:col-span-2">
                    <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
                        <div class="mb-5 flex items-center gap-3">
                            <span class="grid h-9 w-9 place-items-center rounded-md bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 5h16v14H4zM8 9h8M8 13h5" /></svg></span>
                            <div><h3 class="text-sm font-semibold">Promotion details</h3><p class="text-xs text-slate-500">Internal identity and staff-facing description</p></div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div><label for="name" class="mb-1.5 block text-sm font-semibold">Name <span class="text-red-600">*</span></label><input wire:model="name" id="name" type="text" maxlength="255" autocomplete="off" placeholder="e.g. Weekend pipe discount" class="{{ $fieldClass }}">@error('name')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                            <div><label for="code" class="mb-1.5 block text-sm font-semibold">Reference code</label><input wire:model="code" id="code" type="text" maxlength="40" autocomplete="off" placeholder="Optional internal code" class="{{ $fieldClass }} font-mono uppercase">@error('code')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                            <div class="sm:col-span-2"><label for="description" class="mb-1.5 block text-sm font-semibold">Description</label><textarea wire:model="description" id="description" rows="3" placeholder="Describe when staff should expect this promotion to apply" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm outline-none focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/20 dark:border-slate-700 dark:bg-slate-950"></textarea>@error('description')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                        </div>
                    </section>

                    <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
                        <div class="mb-5 flex items-center gap-3">
                            <span class="grid h-9 w-9 place-items-center rounded-md bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300"><svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2v20M7 6h8.5a3.5 3.5 0 010 7H9a3.5 3.5 0 000 7h8" /></svg></span>
                            <div><h3 class="text-sm font-semibold">Reward</h3><p class="text-xs text-slate-500">What the customer receives when every condition matches</p></div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <div><label for="reward_type" class="mb-1.5 block text-sm font-semibold">Reward type <span class="text-red-600">*</span></label><select wire:model.live="reward_type" id="reward_type" class="{{ $fieldClass }}"><option value="percent_off">Percentage off</option><option value="amount_off">Amount off</option><option value="fixed_price">Fixed price</option><option value="bogo">Buy one, get one</option><option value="free_item">Free item</option></select>@error('reward_type')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                            @unless (in_array($reward_type, ['bogo', 'free_item']))
                                <div><label for="reward_value" class="mb-1.5 block text-sm font-semibold">{{ $reward_type === 'percent_off' ? 'Percentage' : 'Amount' }} <span class="text-red-600">*</span></label><div class="relative"><input wire:model="reward_value" id="reward_value" type="text" inputmode="decimal" class="{{ $fieldClass }} pr-10 text-right tabular-nums"><span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-sm text-slate-500">{{ $reward_type === 'percent_off' ? '%' : '' }}</span></div>@error('reward_value')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                            @endunless
                            @if ($reward_type === 'free_item')
                                <div class="sm:col-span-2 lg:col-span-1"><label for="reward_item_id" class="mb-1.5 block text-sm font-semibold">Free item <span class="text-red-600">*</span></label><select wire:model="reward_item_id" id="reward_item_id" class="{{ $fieldClass }}"><option value="">Select an item</option>@foreach ($items as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select>@error('reward_item_id')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                            @endif
                            <div><label for="priority" class="mb-1.5 block text-sm font-semibold">Priority</label><input wire:model="priority" id="priority" type="number" min="0" class="{{ $fieldClass }} text-right tabular-nums">@error('priority')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                        </div>
                        @if ($reward_type === 'bogo')
                            <p class="mt-4 rounded-md bg-sky-50 px-3 py-2 text-sm text-sky-800 dark:bg-sky-950/30 dark:text-sky-300">The cheapest matching unit becomes free. Add a quantity condition below to define how many units must be purchased.</p>
                        @elseif ($reward_type === 'free_item')
                            <p class="mt-4 rounded-md bg-sky-50 px-3 py-2 text-sm text-sky-800 dark:bg-sky-950/30 dark:text-sky-300">The selected free item must be present in the cart as its own line.</p>
                        @endif
                    </section>

                    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-3 dark:border-slate-800 sm:px-5">
                            <div><h3 class="text-sm font-semibold">Eligibility conditions</h3><p class="mt-0.5 text-xs text-slate-500">Every condition below must match</p></div>
                            <button type="button" wire:click="addCondition" class="inline-flex h-9 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-md border border-slate-300 px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>Add condition</button>
                        </div>
                        <div class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($conditions as $index => $condition)
                                @php $isListSubject = in_array($condition['subject'], ['item', 'category', 'customer_group'], true); @endphp
                                <div wire:key="promotion-condition-{{ $index }}" class="p-4 sm:p-5">
                                    <div class="mb-3 flex items-center justify-between lg:hidden"><span class="text-xs font-semibold uppercase text-slate-500">Condition {{ $index + 1 }}</span><button type="button" wire:click="removeCondition({{ $index }})" class="grid h-9 w-9 place-items-center rounded-md text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/30" aria-label="Remove condition {{ $index + 1 }}"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 7h12m-10 0l1 13h6l1-13M9 7V4h6v3" /></svg></button></div>
                                    <div class="grid gap-3 lg:grid-cols-[2rem_minmax(9rem,0.85fr)_minmax(10rem,0.85fr)_minmax(14rem,1.5fr)_2.5rem] lg:items-start">
                                        <div class="hidden h-10 items-center justify-center font-mono text-xs font-semibold text-slate-400 lg:flex">{{ $index + 1 }}</div>
                                        <div><label for="condition_subject_{{ $index }}" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500">Apply to</label><select wire:model.live="conditions.{{ $index }}.subject" id="condition_subject_{{ $index }}" class="{{ $fieldClass }}"><option value="item">Specific items</option><option value="category">Categories</option><option value="customer_group">Customer group</option><option value="cart_total">Cart total</option><option value="quantity">Item quantity</option></select></div>
                                        <div><label for="condition_operator_{{ $index }}" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500">Rule</label><select wire:model="conditions.{{ $index }}.operator" id="condition_operator_{{ $index }}" class="{{ $fieldClass }}">@if ($isListSubject)<option value="in">Is one of</option><option value="not_in">Is not one of</option>@else<option value="gte">At least</option><option value="lte">At most</option><option value="eq">Exactly</option>@endif</select>@error("conditions.{$index}.operator")<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                                        <div><label for="condition_value_{{ $index }}" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500">Value</label>
                                            @if ($condition['subject'] === 'item')
                                                <select multiple size="5" wire:model="conditions.{{ $index }}.value" id="condition_value_{{ $index }}" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">@foreach ($items as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select>
                                            @elseif ($condition['subject'] === 'category')
                                                <select multiple size="5" wire:model="conditions.{{ $index }}.value" id="condition_value_{{ $index }}" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">@foreach ($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select>
                                            @elseif ($condition['subject'] === 'customer_group')
                                                <select multiple size="5" wire:model="conditions.{{ $index }}.value" id="condition_value_{{ $index }}" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-950">@foreach ($loyaltyPackages as $package)<option value="{{ $package->id }}">{{ $package->name }}</option>@endforeach</select>
                                            @elseif ($condition['subject'] === 'cart_total')
                                                <input wire:model="conditions.{{ $index }}.value" id="condition_value_{{ $index }}" type="text" inputmode="decimal" placeholder="0.00" class="{{ $fieldClass }} text-right tabular-nums">
                                            @else
                                                <input wire:model="conditions.{{ $index }}.value" id="condition_value_{{ $index }}" type="number" min="0" step="1" placeholder="0" class="{{ $fieldClass }} text-right tabular-nums">
                                            @endif
                                            @error("conditions.{$index}.value")<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror
                                        </div>
                                        <button type="button" wire:click="removeCondition({{ $index }})" class="mt-6 hidden h-10 w-10 place-items-center rounded-md text-slate-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/30 lg:grid" aria-label="Remove condition {{ $index + 1 }}" title="Remove condition"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 7h12m-10 0l1 13h6l1-13M9 7V4h6v3" /></svg></button>
                                    </div>
                                </div>
                            @empty
                                <div class="px-5 py-10 text-center"><p class="text-sm font-semibold text-slate-700 dark:text-slate-200">No conditions added</p><p class="mt-1 text-sm text-slate-500">Add at least one condition before saving.</p></div>
                            @endforelse
                        </div>
                    </section>
                </div>

                <aside class="space-y-4 xl:sticky xl:top-20">
                    <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <h3 class="text-sm font-semibold">Availability</h3><p class="mt-0.5 text-xs text-slate-500">Date, day, and time restrictions</p>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                            <div><label for="starts_at" class="mb-1.5 block text-sm font-semibold">Starts</label><input wire:model="starts_at" id="starts_at" type="datetime-local" class="{{ $fieldClass }}">@error('starts_at')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                            <div><label for="ends_at" class="mb-1.5 block text-sm font-semibold">Ends</label><input wire:model="ends_at" id="ends_at" type="datetime-local" class="{{ $fieldClass }}">@error('ends_at')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                        </div>
                        <fieldset class="mt-4"><legend class="text-sm font-semibold">Active days</legend><div class="mt-2 grid grid-cols-4 gap-1.5 sm:grid-cols-7 xl:grid-cols-4">@foreach (['1' => 'Mon', '2' => 'Tue', '3' => 'Wed', '4' => 'Thu', '5' => 'Fri', '6' => 'Sat', '7' => 'Sun'] as $value => $label)<label class="relative"><input wire:model="active_days" type="checkbox" value="{{ $value }}" class="peer sr-only"><span class="flex h-9 items-center justify-center rounded-md border border-slate-300 text-xs font-semibold text-slate-600 peer-checked:border-emerald-600 peer-checked:bg-emerald-600 peer-checked:text-white dark:border-slate-700 dark:text-slate-300">{{ $label }}</span></label>@endforeach</div><p class="mt-2 text-xs text-slate-500">No selection means every day.</p></fieldset>
                        <div class="mt-4 grid grid-cols-2 gap-3"><div><label for="active_from" class="mb-1.5 block text-sm font-semibold">From</label><input wire:model="active_from" id="active_from" type="time" class="{{ $fieldClass }}"></div><div><label for="active_to" class="mb-1.5 block text-sm font-semibold">Until</label><input wire:model="active_to" id="active_to" type="time" class="{{ $fieldClass }}"></div></div>
                    </section>

                    <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <h3 class="text-sm font-semibold">Behavior</h3>
                        <div class="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
                            <label class="flex cursor-pointer items-start justify-between gap-4 py-3"><span><span class="block text-sm font-semibold">Active</span><span class="mt-0.5 block text-xs text-slate-500">Available to the promotion engine</span></span><input wire:model="is_active" type="checkbox" class="mt-0.5 h-5 w-5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"></label>
                            <label class="flex cursor-pointer items-start justify-between gap-4 py-3"><span><span class="block text-sm font-semibold">Coupon required</span><span class="mt-0.5 block text-xs text-slate-500">Cashier must enter a valid code</span></span><input wire:model="requires_coupon" type="checkbox" class="mt-0.5 h-5 w-5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"></label>
                            <label class="flex cursor-pointer items-start justify-between gap-4 py-3"><span><span class="block text-sm font-semibold">Stackable</span><span class="mt-0.5 block text-xs text-slate-500">Can combine with other promotions</span></span><input wire:model="stackable" type="checkbox" class="mt-0.5 h-5 w-5 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"></label>
                        </div>
                    </section>

                    <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <h3 class="text-sm font-semibold">Redemption limits</h3><p class="mt-0.5 text-xs text-slate-500">Leave blank for no limit</p>
                        <div class="mt-4 space-y-4"><div><label for="max_redemptions" class="mb-1.5 block text-sm font-semibold">Overall maximum</label><input wire:model="max_redemptions" id="max_redemptions" type="number" min="1" placeholder="Unlimited" class="{{ $fieldClass }} text-right tabular-nums">@error('max_redemptions')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror</div><div><label for="max_redemptions_per_customer" class="mb-1.5 block text-sm font-semibold">Per customer</label><input wire:model="max_redemptions_per_customer" id="max_redemptions_per_customer" type="number" min="1" placeholder="Unlimited" class="{{ $fieldClass }} text-right tabular-nums">@error('max_redemptions_per_customer')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror</div></div>
                    </section>
                </aside>
            </div>

            <section class="flex flex-col-reverse gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:flex-row sm:items-center sm:justify-end">
                <a href="{{ route('promotions.index') }}" class="inline-flex h-10 items-center justify-center px-3 text-sm font-semibold text-slate-600 dark:text-slate-300">Cancel</a>
                <button type="submit" wire:loading.attr="disabled" wire:target="save" class="inline-flex h-10 min-w-40 items-center justify-center rounded-md bg-emerald-600 px-4 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"><span wire:loading.remove wire:target="save">{{ $promotion ? 'Save changes' : 'Create promotion' }}</span><span wire:loading wire:target="save">Saving&hellip;</span></button>
            </section>
        </form>

        @if ($promotion)
            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-200 px-4 py-4 dark:border-slate-800 sm:px-5"><h3 class="text-sm font-semibold">Coupon codes</h3><p class="mt-0.5 text-xs text-slate-500">Issue restricted or reusable codes for this promotion</p></div>
                <div class="grid gap-3 border-b border-slate-200 p-4 dark:border-slate-800 sm:grid-cols-2 lg:grid-cols-[1fr_1.25fr_8rem_11rem_auto] sm:p-5">
                    <div><label for="coupon_code" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500">Code</label><input wire:model="newCoupon.code" id="coupon_code" type="text" maxlength="64" autocomplete="off" placeholder="e.g. SAVE20" class="{{ $fieldClass }} font-mono uppercase">@error('newCoupon.code')<p class="mt-1.5 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                    <div><label for="coupon_customer" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500">Customer</label><select wire:model="newCoupon.customer_id" id="coupon_customer" class="{{ $fieldClass }}"><option value="">Any customer</option>@foreach ($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->company_name }}</option>@endforeach</select></div>
                    <div><label for="coupon_uses" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500">Max uses</label><input wire:model="newCoupon.max_uses" id="coupon_uses" type="number" min="1" class="{{ $fieldClass }} text-right tabular-nums"></div>
                    <div><label for="coupon_expiry" class="mb-1.5 block text-xs font-semibold uppercase text-slate-500">Expires</label><input wire:model="newCoupon.expires_at" id="coupon_expiry" type="date" class="{{ $fieldClass }}"></div>
                    <button type="button" wire:click="generateCoupon" wire:loading.attr="disabled" wire:target="generateCoupon" class="inline-flex h-10 self-end items-center justify-center rounded-md bg-slate-900 px-4 text-sm font-semibold text-white hover:bg-slate-800 disabled:opacity-60 dark:bg-white dark:text-slate-950"><span wire:loading.remove wire:target="generateCoupon">Generate</span><span wire:loading wire:target="generateCoupon">Generating&hellip;</span></button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold uppercase text-slate-500 dark:bg-slate-950/50"><tr><th class="px-4 py-3 sm:px-5">Code</th><th class="px-4 py-3">Customer</th><th class="px-4 py-3">Usage</th><th class="px-4 py-3">Expires</th></tr></thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @forelse ($promotion->coupons as $coupon)
                                <tr><td class="px-4 py-3 font-mono font-semibold sm:px-5">{{ $coupon->code }}</td><td class="px-4 py-3 text-slate-500">{{ $coupon->customer?->company_name ?? 'Any customer' }}</td><td class="px-4 py-3 tabular-nums text-slate-500">{{ $coupon->use_count }} / {{ $coupon->max_uses }}</td><td class="px-4 py-3 text-slate-500">{{ $coupon->expires_at?->toDateString() ?? 'Never' }}</td></tr>
                            @empty
                                <tr><td colspan="4" class="px-5 py-10 text-center text-slate-500">No coupon codes issued yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</div>
