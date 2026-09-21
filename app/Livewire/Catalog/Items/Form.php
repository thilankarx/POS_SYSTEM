<?php

declare(strict_types=1);

namespace App\Livewire\Catalog\Items;

use App\Domain\Catalog\Actions\AssignItemBarcodeAction;
use App\Domain\Catalog\Actions\GenerateItemSkuAction;
use App\Domain\Catalog\Models\AttributeDefinition;
use App\Domain\Catalog\Models\AttributeLink;
use App\Domain\Catalog\Models\AttributeValue;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\ItemBarcode;
use App\Domain\Crm\Models\Supplier;
use App\Domain\Taxation\Models\TaxCategory;
use App\Settings\BusinessProfileSettings;
use App\Support\Money\Rules\ValidDecimal;
use App\Support\Money\Rules\ValidMoneyAmount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Form extends Component
{
    public ?Item $item = null;

    public string $skuPreview = '';

    public string $name = '';

    public string $description = '';

    public ?int $category_id = null;

    /**
     * Business types that sell this item (retail | hardware | restaurant).
     * Empty = sold everywhere; a non-empty list hides it from every other
     * business type in the register, catalog and reports.
     *
     * @var array<int, string>
     */
    public array $business_types = [];

    public ?int $supplier_id = null;

    public ?int $tax_category_id = null;

    /** Only meaningful for a non-stocked item -- a stocked item's price always lives on its stock lot. */
    public string $unit_price = '0.00';

    public string $stock_type = Item::STOCK_TYPE_STOCKED;

    public string $reorder_level = '0';

    public string $reorder_quantity = '';

    public bool $is_active = true;

    public bool $is_serialized = false;

    public bool $has_expiry = false;

    public string $barcode = '';

    /** @var array<int, array{attribute_definition_id: ?int, value: string}> */
    public array $itemAttributes = [];

    public function mount(?Item $item = null): void
    {
        $this->item = $item;

        if ($item !== null) {
            Gate::authorize('update', $item);

            $this->skuPreview = $item->sku;
            $this->name = $item->name;
            $this->description = (string) $item->description;
            $this->category_id = $item->category_id;
            $this->business_types = $item->business_types ?? [];
            $this->supplier_id = $item->supplier_id;
            $this->tax_category_id = $item->tax_category_id;
            $this->unit_price = $item->unit_price !== null ? (string) $item->unit_price->getAmount() : '0.00';
            $this->stock_type = $item->stock_type;
            $this->reorder_level = (string) $item->reorder_level;
            $this->reorder_quantity = $item->reorder_quantity !== null ? (string) $item->reorder_quantity : '';
            $this->is_active = $item->is_active;
            $this->is_serialized = $item->is_serialized;
            $this->has_expiry = $item->has_expiry;
            $this->barcode = (string) $item->barcodes()->where('is_primary', true)->value('barcode');
            $this->itemAttributes = $item->attributeLinks()->with('value.definition')->get()
                ->map(fn (AttributeLink $link) => [
                    'attribute_definition_id' => $link->value->attribute_definition_id,
                    'value' => $link->value->display(),
                ])->all();
        } else {
            Gate::authorize('create', Item::class);
            $this->refreshSkuPreview();
        }
    }

    public function updatedCategoryId(): void
    {
        $this->refreshSkuPreview();
    }

    private function refreshSkuPreview(): void
    {
        if ($this->item !== null) {
            return;
        }

        // The barcode field defaults to the SKU preview so every new item
        // gets a scannable primary barcode with no extra data entry; once
        // the customer types their own value in, further category changes
        // (which shift the preview) stop overwriting it.
        $previousPreview = $this->skuPreview;
        $category = $this->category_id !== null ? Category::find($this->category_id) : null;
        $this->skuPreview = app(GenerateItemSkuAction::class)->preview($category);

        if ($this->barcode === '' || $this->barcode === $previousPreview) {
            $this->barcode = $this->skuPreview;
        }
    }

    public function addAttributeRow(): void
    {
        $this->itemAttributes[] = ['attribute_definition_id' => null, 'value' => ''];
    }

    public function removeAttributeRow(int $index): void
    {
        unset($this->itemAttributes[$index]);
        $this->itemAttributes = array_values($this->itemAttributes);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'business_types' => ['array'],
            'business_types.*' => [Rule::in(BusinessProfileSettings::businessTypes())],
            'supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')],
            'tax_category_id' => ['nullable', 'integer', Rule::exists('tax_categories', 'id')],
            // Only a non-stocked item (service/amount-entry) prices from
            // this field -- a stocked item's price always comes from its
            // stock lot, set at receiving, not here.
            'unit_price' => [$this->stock_type === Item::STOCK_TYPE_STOCKED ? 'nullable' : 'required', new ValidMoneyAmount],
            'stock_type' => ['required', Rule::in([Item::STOCK_TYPE_STOCKED, Item::STOCK_TYPE_SERVICE, 'amount_entry'])],
            'reorder_level' => ['required', new ValidDecimal],
            'reorder_quantity' => ['nullable', new ValidDecimal],
            'is_active' => ['boolean'],
            'is_serialized' => ['boolean'],
            'has_expiry' => ['boolean'],
            'barcode' => ['nullable', 'string', 'max:255', Rule::unique('item_barcodes', 'barcode')->ignore(
                $this->item?->barcodes()->where('is_primary', true)->value('id')
            )],
            'itemAttributes' => ['array'],
            'itemAttributes.*.attribute_definition_id' => ['nullable', Rule::exists('attribute_definitions', 'id')],
            'itemAttributes.*.value' => ['nullable', 'string'],
        ];
    }

    public function save(): void
    {
        $this->withValidator(function ($validator) {
            $validator->after(function ($validator) {
                $definitions = AttributeDefinition::whereIn('id', array_filter(array_column($this->itemAttributes, 'attribute_definition_id')))
                    ->get()->keyBy('id');

                foreach ($this->itemAttributes as $index => $row) {
                    if (empty($row['attribute_definition_id']) || $row['value'] === '') {
                        continue;
                    }

                    $definition = $definitions->get((int) $row['attribute_definition_id']);

                    if ($definition === null) {
                        continue;
                    }

                    if ($definition->type === AttributeDefinition::TYPE_DECIMAL && ! is_numeric($row['value'])) {
                        $validator->errors()->add("itemAttributes.{$index}.value", 'This attribute requires a numeric value.');
                    }

                    if ($definition->type === AttributeDefinition::TYPE_DATE && strtotime($row['value']) === false) {
                        $validator->errors()->add("itemAttributes.{$index}.value", 'This attribute requires a valid date.');
                    }
                }
            });
        });

        $validated = $this->validate();
        $barcode = $validated['barcode'];
        $attributeRows = $validated['itemAttributes'];
        unset($validated['barcode'], $validated['itemAttributes']);
        $validated['reorder_quantity'] = $validated['reorder_quantity'] !== null && $validated['reorder_quantity'] !== ''
            ? $validated['reorder_quantity']
            : null;
        $validated['business_types'] = array_values(array_unique($validated['business_types'] ?? []));
        // A stocked item never has its own price -- force null (not '0.00')
        // so it correctly reads as "no price yet" until a receiving prices
        // one of its lots, rather than looking like a free item.
        if ($validated['stock_type'] === Item::STOCK_TYPE_STOCKED) {
            $validated['unit_price'] = null;
        }

        $isNewItem = $this->item === null;

        if ($this->item !== null) {
            Gate::authorize('update', $this->item);
            $this->item->update($validated);
        } else {
            Gate::authorize('create', Item::class);
            $category = $validated['category_id'] !== null ? Category::find($validated['category_id']) : null;

            $this->item = DB::transaction(function () use ($validated, $category) {
                $validated['sku'] = app(GenerateItemSkuAction::class)->execute($category);

                return Item::create($validated);
            });
        }

        if ($barcode !== null && $barcode !== '') {
            ItemBarcode::updateOrCreate(
                ['item_id' => $this->item->id, 'is_primary' => true],
                ['barcode' => $barcode],
            );
        } elseif ($isNewItem) {
            // Every new item gets a primary barcode even if the customer
            // cleared the pre-filled field -- falls back to the item's
            // actual (burned, not just previewed) SKU.
            app(AssignItemBarcodeAction::class)->execute($this->item);
        }

        $this->syncAttributes($attributeRows);

        session()->flash('status', 'Item saved.');
        $this->redirectRoute('items.index');
    }

    /**
     * @param  array<int, array{attribute_definition_id: ?int, value: ?string}>  $rows
     */
    private function syncAttributes(array $rows): void
    {
        $this->item->attributeLinks()->delete();

        $definitions = AttributeDefinition::whereIn('id', array_filter(array_column($rows, 'attribute_definition_id')))
            ->get()->keyBy('id');

        foreach ($rows as $row) {
            if (empty($row['attribute_definition_id']) || $row['value'] === null || $row['value'] === '') {
                continue;
            }

            $definition = $definitions->get((int) $row['attribute_definition_id']);

            if ($definition === null) {
                continue;
            }

            $column = match ($definition->type) {
                AttributeDefinition::TYPE_DECIMAL => 'value_decimal',
                AttributeDefinition::TYPE_DATE => 'value_date',
                AttributeDefinition::TYPE_CHECKBOX => 'value_boolean',
                default => 'value_text',
            };

            $value = match ($column) {
                'value_boolean' => (bool) $row['value'],
                'value_text' => trim($row['value']),
                default => $row['value'],
            };

            $attributeValue = AttributeValue::firstOrCreate([
                'attribute_definition_id' => $definition->id,
                $column => $value,
            ]);

            AttributeLink::firstOrCreate([
                'attribute_value_id' => $attributeValue->id,
                'attributable_type' => Item::class,
                'attributable_id' => $this->item->id,
            ]);
        }
    }

    public function render()
    {
        return view('livewire.catalog.items.form', [
            'categories' => Category::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('company_name')->get(),
            'taxCategories' => TaxCategory::orderBy('name')->get(),
            'attributeDefinitions' => AttributeDefinition::where('type', '!=', AttributeDefinition::TYPE_GROUP)->with('values')->orderBy('name')->get(),
        ]);
    }
}
