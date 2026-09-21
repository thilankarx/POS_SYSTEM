<div>
    @section('title', 'Business profile')

    <div class="mx-auto max-w-6xl space-y-6">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm font-medium text-emerald-700 dark:text-emerald-400">Settings</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-950 dark:text-white">Business profile</h2>
                <p class="mt-1 max-w-2xl text-sm text-slate-600 dark:text-slate-400">Manage the identity and receipt details customers see when you trade.</p>
            </div>
            @if (session('status'))
                <p role="status" class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-300">{{ session('status') }}</p>
            @endif
        </div>

        <form wire:submit="save" class="space-y-5">
            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <header class="border-b border-slate-200 px-5 py-4 dark:border-slate-800 sm:px-6">
                    <h3 class="text-base font-semibold text-slate-950 dark:text-white">Store details</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Business name and contact information used on sales documents.</p>
                </header>
                <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
                    <div class="sm:col-span-2">
                        <label for="store_name" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Store name <span class="text-rose-600">*</span></label>
                        <input wire:model="store_name" id="store_name" type="text" autocomplete="organization" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        @error('store_name') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="phone" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Phone</label>
                        <input wire:model="phone" id="phone" type="tel" autocomplete="tel" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        @error('phone') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="address" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Address</label>
                        <input wire:model="address" id="address" type="text" autocomplete="street-address" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                        @error('address') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <header class="border-b border-slate-200 px-5 py-4 dark:border-slate-800 sm:px-6">
                    <h3 class="text-base font-semibold text-slate-950 dark:text-white">Register type</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Choose the workflow that best fits this business.</p>
                </header>
                <div class="grid gap-3 p-5 sm:grid-cols-3 sm:p-6">
                    @foreach ([
                        'retail' => ['Retail', 'General shop and counter sales'],
                        'hardware' => ['Hardware', 'Location stock and flexible quantities'],
                        'restaurant' => ['Restaurant', 'Table service and kitchen workflow'],
                    ] as $type => [$label, $description])
                        <label class="flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition has-[:checked]:border-emerald-600 has-[:checked]:bg-emerald-50/70 dark:has-[:checked]:border-emerald-500 dark:has-[:checked]:bg-emerald-950/25 {{ $business_type === $type ? 'border-emerald-600 bg-emerald-50/70 dark:border-emerald-500 dark:bg-emerald-950/25' : 'border-slate-200 dark:border-slate-700' }}">
                            <input wire:model="business_type" type="radio" name="business_type" value="{{ $type }}" class="mt-0.5 h-4 w-4 accent-emerald-600">
                            <span class="min-w-0">
                                <span class="block text-sm font-semibold text-slate-900 dark:text-white">{{ $label }}</span>
                                <span class="mt-1 block text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $description }}</span>
                            </span>
                        </label>
                    @endforeach
                    @error('business_type') <p class="text-sm text-rose-600 sm:col-span-3">{{ $message }}</p> @enderror
                </div>
                <p class="px-5 pb-5 text-xs text-slate-500 dark:text-slate-400 sm:px-6 sm:pb-6">This setting changes the available register workflow for all POS users.</p>
            </section>

            <section class="overflow-hidden rounded-lg border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <header class="border-b border-slate-200 px-5 py-4 dark:border-slate-800 sm:px-6">
                    <h3 class="text-base font-semibold text-slate-950 dark:text-white">Receipt branding</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Set the logo and short messages printed on receipts.</p>
                </header>
                <div class="grid gap-6 p-5 sm:grid-cols-[minmax(0,1fr)_minmax(240px,0.8fr)] sm:p-6">
                    <div class="space-y-5">
                        <div>
                            <label for="logo" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Store logo</label>
                            @if ($logo_path && ! $logo)
                                <div class="mb-3 flex items-center justify-between gap-3 rounded-md border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-950">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logo_path) }}" alt="Current store logo" class="h-12 max-w-32 object-contain">
                                        <span class="truncate text-xs text-slate-500 dark:text-slate-400">Current logo</span>
                                    </div>
                                    <button type="button" wire:click="removeLogo" wire:confirm="Remove the logo?" class="shrink-0 rounded-md px-2 py-1 text-sm font-medium text-rose-700 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/40">Remove</button>
                                </div>
                            @endif
                            <input wire:model="logo" id="logo" type="file" accept="image/png" class="block w-full rounded-md border border-slate-300 bg-white text-sm text-slate-700 file:mr-3 file:border-0 file:bg-slate-100 file:px-3 file:py-2.5 file:text-sm file:font-medium file:text-slate-700 hover:file:bg-slate-200 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-300 dark:file:bg-slate-800 dark:file:text-slate-200">
                            <div wire:loading wire:target="logo" class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">Uploading logo…</div>
                            @error('logo') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                            <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400">PNG only, up to 300 × 180 px and 2 MB.</p>
                        </div>
                        <div>
                            <label for="receipt_header" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Receipt header message</label>
                            <input wire:model="receipt_header" id="receipt_header" type="text" maxlength="255" placeholder="e.g. Open 7 days a week" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            @error('receipt_header') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="receipt_footer" class="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">Receipt footer message</label>
                            <input wire:model="receipt_footer" id="receipt_footer" type="text" maxlength="255" placeholder="e.g. Thank you for shopping with us" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-950 shadow-sm outline-none transition focus:border-emerald-600 focus:ring-2 focus:ring-emerald-600/20 dark:border-slate-700 dark:bg-slate-950 dark:text-white">
                            @error('receipt_footer') <p class="mt-1.5 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <aside class="self-start rounded-lg border border-dashed border-slate-300 bg-slate-50 p-5 text-center dark:border-slate-700 dark:bg-slate-950/70">
                        <p class="mb-4 text-left text-xs font-semibold uppercase text-slate-500 dark:text-slate-400">Receipt preview</p>
                        @if ($logo && $logo->isPreviewable())
                            <img src="{{ $logo->temporaryUrl() }}" alt="New store logo preview" class="mx-auto mb-3 max-h-16 max-w-40 object-contain">
                        @elseif ($logo_path)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logo_path) }}" alt="Store logo preview" class="mx-auto mb-3 max-h-16 max-w-40 object-contain">
                        @else
                            <div class="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-md bg-slate-200 text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7.5A2.5 2.5 0 015.5 5h13A2.5 2.5 0 0121 7.5v9a2.5 2.5 0 01-2.5 2.5h-13A2.5 2.5 0 013 16.5v-9zM3 8l9 6 9-6" /></svg>
                            </div>
                        @endif
                        <p class="break-words text-sm font-bold text-slate-900 dark:text-white">{{ $store_name ?: 'Your store name' }}</p>
                        @if ($address)<p class="mt-1 break-words text-xs text-slate-600 dark:text-slate-400">{{ $address }}</p>@endif
                        @if ($phone)<p class="mt-1 text-xs text-slate-600 dark:text-slate-400">{{ $phone }}</p>@endif
                        @if ($receipt_header)<p class="mt-3 border-t border-dashed border-slate-300 pt-3 text-xs text-slate-600 dark:border-slate-700 dark:text-slate-400">{{ $receipt_header }}</p>@endif
                        <p class="mt-4 border-t border-dashed border-slate-300 pt-3 text-[11px] text-slate-400 dark:border-slate-700">Items and totals appear here</p>
                        @if ($receipt_footer)<p class="mt-3 border-t border-dashed border-slate-300 pt-3 text-xs text-slate-600 dark:border-slate-700 dark:text-slate-400">{{ $receipt_footer }}</p>@endif
                    </aside>
                </div>
            </section>

            <div class="sticky bottom-0 z-10 -mx-1 flex flex-col-reverse gap-3 border-t border-slate-200 bg-white/95 px-1 py-3 backdrop-blur sm:flex-row sm:items-center sm:justify-between dark:border-slate-800 dark:bg-slate-950/95">
                <p class="text-xs text-slate-500 dark:text-slate-400">Changes apply to new receipts and POS sessions.</p>
                <button type="submit" wire:loading.attr="disabled" wire:target="save,logo" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-emerald-700 px-5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-emerald-600 dark:hover:bg-emerald-500">
                    <svg wire:loading.remove wire:target="save" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7M4 5h12l4 4v10H4z" /></svg>
                    <svg wire:loading wire:target="save" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" /><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z" /></svg>
                    <span wire:loading.remove wire:target="save">Save changes</span>
                    <span wire:loading wire:target="save">Saving changes…</span>
                </button>
            </div>
        </form>
    </div>
</div>
