<div>
    @section('title', $item ? 'Edit item' : 'New item')

    <div class="max-w-2xl rounded-lg border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <form wire:submit="save" class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="sku" class="mb-1 block text-sm font-medium">SKU</label>
                    <input value="{{ $skuPreview }}" id="sku" type="text" readonly disabled class="w-full rounded-md border border-gray-300 bg-gray-100 px-3 py-2 text-sm text-gray-600 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        @if ($item)
                            Assigned automatically when the item was created; it cannot be changed.
                        @else
                            Auto-generated from the selected category (or "GEN-" if none) when you save.
                        @endif
                    </p>
                </div>

                <div>
                    <label for="name" class="mb-1 block text-sm font-medium">Name</label>
                    <input wire:model="name" id="name" type="text" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="description" class="mb-1 block text-sm font-medium">Description</label>
                <textarea wire:model="description" id="description" rows="2" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800"></textarea>
                @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label for="category_id" class="mb-1 block text-sm font-medium">Category</label>
                    <select wire:model.live="category_id" id="category_id" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                        <option value="">None</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="supplier_id" class="mb-1 block text-sm font-medium">Supplier</label>
                    <select wire:model="supplier_id" id="supplier_id" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                        <option value="">None</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->company_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="tax_category_id" class="mb-1 block text-sm font-medium">Tax category</label>
                    <select wire:model="tax_category_id" id="tax_category_id" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                        <option value="">None</option>
                        @foreach ($taxCategories as $taxCategory)
                            <option value="{{ $taxCategory->id }}">{{ $taxCategory->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Sold by business type</label>
                <div class="flex flex-wrap gap-4">
                    @foreach (\App\Settings\BusinessProfileSettings::businessTypes() as $type)
                        <label class="inline-flex items-center gap-2 text-sm capitalize">
                            <input type="checkbox" wire:model="business_types" value="{{ $type }}" class="rounded border-gray-300">
                            {{ $type }}
                        </label>
                    @endforeach
                </div>
                @error('business_types.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Leave all unchecked to sell this item everywhere. Ticking one or more hides it from the register, catalog and reports of every other business type.</p>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label for="stock_type" class="mb-1 block text-sm font-medium">Stock type</label>
                    <select wire:model.live="stock_type" id="stock_type" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                        <option value="stocked">Stocked</option>
                        <option value="service">Service</option>
                        <option value="amount_entry">Amount entry</option>
                    </select>
                </div>

                @if ($stock_type !== \App\Domain\Catalog\Models\Item::STOCK_TYPE_STOCKED)
                    <div>
                        <label for="unit_price" class="mb-1 block text-sm font-medium">Price</label>
                        <input wire:model="unit_price" id="unit_price" type="text" inputmode="decimal" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                        @error('unit_price') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @else
                    <div class="col-span-2 flex items-end">
                        <p class="text-xs text-gray-500 dark:text-gray-400">A stocked item has no price of its own &mdash; every price (cost and selling) is set per lot/batch when stock is received.</p>
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="reorder_level" class="mb-1 block text-sm font-medium">Reorder level</label>
                    <input wire:model="reorder_level" id="reorder_level" type="text" inputmode="decimal" placeholder="0" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">0 means this item never appears in reorder suggestions.</p>
                    @error('reorder_level') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="reorder_quantity" class="mb-1 block text-sm font-medium">Reorder quantity (optional)</label>
                    <input wire:model="reorder_quantity" id="reorder_quantity" type="text" inputmode="decimal" placeholder="Top up to reorder level" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                    @error('reorder_quantity') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="barcode" class="mb-1 block text-sm font-medium">Primary barcode</label>
                <input wire:model="barcode" id="barcode" type="text" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Defaults to the item's SKU &mdash; change it if the item already has a manufacturer or supplier barcode.</p>
                @error('barcode') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input wire:model="is_active" type="checkbox" class="rounded border-gray-300">
                Active
            </label>

            <div class="flex flex-wrap items-center gap-4">
                <label class="flex items-center gap-2 text-sm">
                    <input wire:model="is_serialized" type="checkbox" class="rounded border-gray-300">
                    Tracks individual serial numbers
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input wire:model="has_expiry" type="checkbox" class="rounded border-gray-300">
                    Has an expiry date
                </label>
            </div>

            <div class="mt-2">
                <div class="mb-2 flex items-center justify-between">
                    <span class="text-sm font-medium">Attributes</span>
                    <button type="button" wire:click="addAttributeRow" class="text-sm text-gray-600 hover:underline dark:text-gray-300">+ Add attribute</button>
                </div>

                <div class="space-y-2">
                    @foreach ($itemAttributes as $index => $row)
                        @php $definition = $attributeDefinitions->firstWhere('id', $row['attribute_definition_id']); @endphp
                        <div class="grid grid-cols-12 items-start gap-2">
                            <div class="col-span-5">
                                <select wire:model.live="itemAttributes.{{ $index }}.attribute_definition_id" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                                    <option value="">Select attribute&hellip;</option>
                                    @foreach ($attributeDefinitions as $option)
                                        <option value="{{ $option->id }}">{{ $option->name }}{{ $option->unit ? " ({$option->unit})" : '' }}</option>
                                    @endforeach
                                </select>
                                @error("itemAttributes.{$index}.attribute_definition_id") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="col-span-6">
                                @if ($definition?->type === 'decimal')
                                    <input wire:model="itemAttributes.{{ $index }}.value" type="text" inputmode="decimal" placeholder="Value" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                                @elseif ($definition?->type === 'date')
                                    <input wire:model="itemAttributes.{{ $index }}.value" type="date" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                                @elseif ($definition?->type === 'checkbox')
                                    <select wire:model="itemAttributes.{{ $index }}.value" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                                        <option value="">Select&hellip;</option>
                                        <option value="1">Yes</option>
                                        <option value="0">No</option>
                                    </select>
                                @else
                                    <input wire:model="itemAttributes.{{ $index }}.value" type="text" list="attribute-values-{{ $index }}" placeholder="Value" class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                                    @if ($definition)
                                        <datalist id="attribute-values-{{ $index }}">
                                            @foreach ($definition->values->unique(fn ($value) => $value->display()) as $value)
                                                <option value="{{ $value->display() }}"></option>
                                            @endforeach
                                        </datalist>
                                    @endif
                                @endif
                                @error("itemAttributes.{$index}.value") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <div class="col-span-1 pt-2">
                                <button type="button" wire:click="removeAttributeRow({{ $index }})" class="text-sm text-red-600 hover:underline">✕</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" wire:loading.attr="disabled" wire:target="save" class="rounded-md bg-[#1b1b18] px-4 py-2 text-sm font-medium text-white hover:bg-black disabled:cursor-not-allowed disabled:opacity-60 dark:bg-white dark:text-[#1b1b18]">
                    <span wire:loading.remove wire:target="save">Save</span>
                    <span wire:loading wire:target="save">Saving&hellip;</span>
                </button>
                <a href="{{ route('items.index') }}" class="text-sm text-gray-500 hover:underline dark:text-gray-400">Cancel</a>
            </div>
        </form>
    </div>
</div>
