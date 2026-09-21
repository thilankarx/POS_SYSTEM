<div>
    @section('title', $dinnerTable ? 'Edit dinner table' : 'New dinner table')

    <div class="max-w-2xl rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <form wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="name" class="mb-1 block text-sm font-medium">Name</label>
                    <input wire:model="name" id="name" type="text" placeholder="e.g. Table 5" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="seats" class="mb-1 block text-sm font-medium">Seats</label>
                    <input wire:model="seats" id="seats" type="number" min="0" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                    @error('seats') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="stock_location_id" class="mb-1 block text-sm font-medium">Stock location</label>
                <select wire:model="stock_location_id" id="stock_location_id" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                    <option value="">Select a location&hellip;</option>
                    @foreach ($stockLocations as $location)
                        <option value="{{ $location->id }}">{{ $location->name }}</option>
                    @endforeach
                </select>
                @error('stock_location_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" wire:loading.attr="disabled" wire:target="save" class="rounded-md bg-[#1b1b18] px-4 py-2 text-sm font-medium text-white hover:bg-black disabled:cursor-not-allowed disabled:opacity-60 dark:bg-white dark:text-[#1b1b18]">
                    <span wire:loading.remove wire:target="save">Save</span>
                    <span wire:loading wire:target="save">Saving&hellip;</span>
                </button>
                <a href="{{ route('dinner-tables.index') }}" class="text-sm text-gray-500 hover:underline dark:text-gray-400">Cancel</a>
            </div>
        </form>
    </div>
</div>
