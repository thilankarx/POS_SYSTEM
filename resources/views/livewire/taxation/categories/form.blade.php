<div>
    @section('title', $taxCategory ? "Edit {$taxCategory->name}" : 'New tax category')

    <div class="max-w-3xl">
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <form wire:submit="save" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="name" class="mb-1 block text-sm font-medium">Name</label>
                        <input wire:model="name" id="name" type="text" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="code" class="mb-1 block text-sm font-medium">Code</label>
                        <input wire:model="code" id="code" type="text" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                        @error('code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input wire:model="is_default" type="checkbox" class="rounded border-gray-300">
                    Default category
                </label>

                <div class="mt-6">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-sm font-medium">Rates</span>
                        <button type="button" wire:click="addRate" class="text-sm text-gray-600 hover:underline dark:text-gray-300">+ Add rate</button>
                    </div>

                    <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">
                        A category with no rates is tax-exempt. Multiple rates stack (cascade) in the order listed.
                    </p>

                    <div class="space-y-2">
                        @foreach ($rates as $index => $rate)
                            <div class="grid grid-cols-12 items-start gap-2">
                                <div class="col-span-4">
                                    <input wire:model="rates.{{ $index }}.name" type="text" placeholder="Rate name" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                                    @error("rates.{$index}.name") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div class="col-span-2">
                                    <input wire:model="rates.{{ $index }}.rate" type="text" inputmode="decimal" placeholder="Rate %" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                                    @error("rates.{$index}.rate") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div class="col-span-3">
                                    <input wire:model="rates.{{ $index }}.effective_from" type="date" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                                    @error("rates.{$index}.effective_from") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div class="col-span-2">
                                    <input wire:model="rates.{{ $index }}.effective_to" type="date" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                                    @error("rates.{$index}.effective_to") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                </div>
                                <div class="col-span-1 pt-2">
                                    <button type="button" wire:click="removeRate({{ $index }})" class="text-sm text-red-600 hover:underline">✕</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button type="submit" wire:loading.attr="disabled" wire:target="save" class="rounded-md bg-[#1b1b18] px-4 py-2 text-sm font-medium text-white hover:bg-black disabled:cursor-not-allowed disabled:opacity-60 dark:bg-white dark:text-[#1b1b18]">
                        <span wire:loading.remove wire:target="save">Save</span>
                        <span wire:loading wire:target="save">Saving&hellip;</span>
                    </button>
                    <a href="{{ route('tax-categories.index') }}" class="text-sm text-gray-500 hover:underline dark:text-gray-400">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
