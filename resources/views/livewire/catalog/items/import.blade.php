<div>
    @section('title', 'Import items')

    <div class="max-w-2xl space-y-4">
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">
                Upload a CSV to create new items or update existing ones (matched by SKU).
                Leave the SKU column blank to auto-generate one for new items.
                Leave the barcode column blank to generate a primary barcode from the item's SKU &mdash; fill it in to use a manufacturer or supplier barcode instead.
                <a href="{{ route('items.import.template') }}" class="text-gray-900 underline dark:text-white">Download the template</a>
                for the expected columns.
            </p>

            <form wire:submit="import" class="space-y-4">
                <div>
                    <label for="csv" class="mb-1 block text-sm font-medium">CSV file</label>
                    <input wire:model="csv" id="csv" type="file" accept=".csv,text/csv" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                    @error('csv') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit" wire:loading.attr="disabled" wire:target="import,csv" class="rounded-md bg-[#1b1b18] px-4 py-2 text-sm font-medium text-white hover:bg-black disabled:cursor-not-allowed disabled:opacity-60 dark:bg-white dark:text-[#1b1b18]">
                        <span wire:loading.remove wire:target="import">Import</span>
                        <span wire:loading wire:target="import">Importing&hellip;</span>
                    </button>
                    <a href="{{ route('items.index') }}" class="text-sm text-gray-500 hover:underline dark:text-gray-400">Back</a>
                </div>
            </form>
        </div>

        @if ($missingReferences !== [])
            <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200">
                <p class="mb-2 font-medium">Nothing was imported. Create these first, then re-upload the file:</p>
                <ul class="list-disc space-y-1 pl-5">
                    @if (! empty($missingReferences['category']))
                        <li>Categories: {{ implode(', ', $missingReferences['category']) }} &mdash; <a href="{{ route('categories.create') }}" class="underline">create category</a></li>
                    @endif
                    @if (! empty($missingReferences['supplier']))
                        <li>Suppliers: {{ implode(', ', $missingReferences['supplier']) }} &mdash; <a href="{{ route('suppliers.create') }}" class="underline">create supplier</a></li>
                    @endif
                    @if (! empty($missingReferences['tax_category']))
                        <li>Tax categories: {{ implode(', ', $missingReferences['tax_category']) }} &mdash; <a href="{{ route('tax-categories.create') }}" class="underline">create tax category</a></li>
                    @endif
                </ul>
            </div>
        @endif

        @if ($createdCount > 0 || $updatedCount > 0 || $results !== [])
            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="mb-3 text-sm font-medium">
                    {{ $createdCount }} created, {{ $updatedCount }} updated.
                </div>

                @if ($results !== [])
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-gray-200 text-gray-500 dark:border-gray-700 dark:text-gray-400">
                            <tr>
                                <th class="py-1 pr-4">Row</th>
                                <th class="py-1">Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($results as $result)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="py-1 pr-4">{{ $result['row'] }}</td>
                                    <td @class(['py-1', 'text-red-600' => $result['level'] === 'error', 'text-amber-600' => $result['level'] === 'warning'])>
                                        {{ $result['message'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endif
    </div>
</div>
