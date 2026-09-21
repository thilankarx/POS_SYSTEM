<?php

declare(strict_types=1);

namespace App\Livewire\Catalog\Items;

use App\Domain\Catalog\Actions\AssignItemBarcodeAction;
use App\Domain\Catalog\Actions\GenerateItemSkuAction;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\ItemBarcode;
use App\Domain\Crm\Models\Supplier;
use App\Domain\Taxation\Models\TaxCategory;
use App\Support\Money\Rules\ValidDecimal;
use App\Support\Money\Rules\ValidMoneyAmount;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Import extends Component
{
    use WithFileUploads;

    public ?UploadedFile $csv = null;

    public int $createdCount = 0;

    public int $updatedCount = 0;

    /** @var array<int, array{row: int, message: string, level: string}> */
    public array $results = [];

    /** @var array<string, array<int, string>> */
    public array $missingReferences = [];

    public function mount(): void
    {
        Gate::authorize('items.import');
    }

    public function rules(): array
    {
        return [
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }

    public function import(): void
    {
        Gate::authorize('items.import');
        $this->validate();

        $this->createdCount = 0;
        $this->updatedCount = 0;
        $this->results = [];
        $this->missingReferences = [];

        $handle = fopen($this->csv->getRealPath(), 'r');
        $header = fgetcsv($handle, escape: '');

        if ($header === false) {
            $this->addError('csv', 'The file is empty.');
            fclose($handle);

            return;
        }

        $header = array_map(fn ($col) => strtolower(trim((string) $col)), $header);
        $rows = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle, escape: '')) !== false) {
            $rowNumber++;

            if (count(array_filter($row, fn ($v) => $v !== null && $v !== '')) === 0) {
                continue;
            }

            $data = [];
            foreach ($header as $index => $column) {
                $data[$column] = trim((string) ($row[$index] ?? ''));
            }

            $rows[] = ['row' => $rowNumber, 'data' => $data];
        }

        fclose($handle);

        // Fail fast: if the file references categories/suppliers/tax categories
        // that don't exist yet, import nothing and tell the user what to set up
        // first, rather than silently creating items with those fields blank.
        $this->missingReferences = $this->findMissingReferences($rows);

        if ($this->missingReferences !== []) {
            return;
        }

        foreach ($rows as $entry) {
            $this->processRow($entry['row'], $entry['data']);
        }

        session()->flash('status', "{$this->createdCount} created, {$this->updatedCount} updated.");
        $this->reset('csv');
    }

    /**
     * @param  array<int, array{row: int, data: array<string, string>}>  $rows
     * @return array<string, array<int, string>>
     */
    private function findMissingReferences(array $rows): array
    {
        $checks = [
            'category' => [Category::class, 'name'],
            'supplier' => [Supplier::class, 'company_name'],
            'tax_category' => [TaxCategory::class, 'name'],
        ];

        $missing = [];

        foreach ($checks as $column => [$model, $lookupColumn]) {
            $names = collect($rows)
                ->pluck("data.{$column}")
                ->filter(fn ($name) => $name !== null && $name !== '')
                ->unique()
                ->values();

            if ($names->isEmpty()) {
                continue;
            }

            $found = $model::query()->whereIn($lookupColumn, $names)->pluck($lookupColumn);
            $notFound = $names->diff($found)->values();

            if ($notFound->isNotEmpty()) {
                $missing[$column] = $notFound->all();
            }
        }

        return $missing;
    }

    /**
     * @param  array<string, string>  $data
     */
    private function processRow(int $rowNumber, array $data): void
    {
        $validator = Validator::make($data, [
            'sku' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'unit_price' => ['nullable', new ValidMoneyAmount],
            'stock_type' => ['nullable', Rule::in(['stocked', 'service', 'amount_entry'])],
            'reorder_level' => ['nullable', new ValidDecimal],
            'reorder_quantity' => ['nullable', new ValidDecimal],
            'barcode' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            $this->results[] = [
                'row' => $rowNumber,
                'message' => implode(' ', $validator->errors()->all()),
                'level' => 'error',
            ];

            return;
        }

        $sku = ($data['sku'] ?? '') !== '' ? $data['sku'] : null;
        // A row with no SKU (the common case for a supplier price list with
        // no internal codes) falls back to an exact, case-insensitive name
        // match -- without this, re-importing the same blank-SKU file, or
        // any file listing an item already in the catalog by name, creates
        // a fresh duplicate with a new auto-generated SKU every time instead
        // of updating the one that's already there.
        $existing = $sku !== null
            ? Item::where('sku', $sku)->first()
            : Item::whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) ($data['name'] ?? '')))])->first();
        $isNew = $existing === null;
        $warnings = [];
        $category = null;

        $barcode = ($data['barcode'] ?? '') !== '' ? $data['barcode'] : null;

        if ($isNew && $barcode !== null && ItemBarcode::where('barcode', $barcode)->exists()) {
            $this->results[] = [
                'row' => $rowNumber,
                'message' => "barcode \"{$barcode}\" is already in use by another item, row skipped",
                'level' => 'error',
            ];

            return;
        }

        $attributes = [
            'name' => $data['name'],
        ];

        // A stocked item never has a price of its own -- price only ever
        // comes from a stock lot at receiving, same rule the manual item
        // form enforces. Any unit_price column value in the CSV is only
        // honored for a non-stocked (service/amount-entry) row.
        $effectiveStockType = ($data['stock_type'] ?? '') !== '' ? $data['stock_type'] : ($existing?->stock_type ?? Item::STOCK_TYPE_STOCKED);
        if ($effectiveStockType !== Item::STOCK_TYPE_STOCKED && ($data['unit_price'] ?? '') !== '') {
            $attributes['unit_price'] = $data['unit_price'];
        } elseif ($effectiveStockType === Item::STOCK_TYPE_STOCKED) {
            $attributes['unit_price'] = null;
        }

        if (($data['description'] ?? '') !== '' || $isNew) {
            $attributes['description'] = ($data['description'] ?? '') !== '' ? $data['description'] : null;
        }

        if (($data['category'] ?? '') !== '' || $isNew) {
            $category = ($data['category'] ?? '') !== '' ? Category::where('name', $data['category'])->first() : null;
            $attributes['category_id'] = $category?->id;

            if (($data['category'] ?? '') !== '' && $category === null) {
                $warnings[] = "category \"{$data['category']}\" not found, left blank";
            }
        }

        if (($data['supplier'] ?? '') !== '' || $isNew) {
            $supplier = ($data['supplier'] ?? '') !== '' ? Supplier::where('company_name', $data['supplier'])->first() : null;
            $attributes['supplier_id'] = $supplier?->id;

            if (($data['supplier'] ?? '') !== '' && $supplier === null) {
                $warnings[] = "supplier \"{$data['supplier']}\" not found, left blank";
            }
        }

        if (($data['tax_category'] ?? '') !== '' || $isNew) {
            $taxCategory = ($data['tax_category'] ?? '') !== '' ? TaxCategory::where('name', $data['tax_category'])->first() : null;
            $attributes['tax_category_id'] = $taxCategory?->id;

            if (($data['tax_category'] ?? '') !== '' && $taxCategory === null) {
                $warnings[] = "tax category \"{$data['tax_category']}\" not found, left blank";
            }
        }

        if (($data['stock_type'] ?? '') !== '' || $isNew) {
            $attributes['stock_type'] = ($data['stock_type'] ?? '') !== '' ? $data['stock_type'] : Item::STOCK_TYPE_STOCKED;
        }

        if (($data['reorder_level'] ?? '') !== '' || $isNew) {
            $attributes['reorder_level'] = ($data['reorder_level'] ?? '') !== '' ? $data['reorder_level'] : '0';
        }

        if (($data['reorder_quantity'] ?? '') !== '' || $isNew) {
            $attributes['reorder_quantity'] = ($data['reorder_quantity'] ?? '') !== '' ? $data['reorder_quantity'] : null;
        }

        if (($data['is_active'] ?? '') !== '' || $isNew) {
            $attributes['is_active'] = ($data['is_active'] ?? '') !== '' ? (bool) $data['is_active'] : true;
        }

        $sku ??= $existing?->sku ?? app(GenerateItemSkuAction::class)->execute($category);
        $attributes['sku'] = $sku;

        $item = Item::updateOrCreate(['sku' => $sku], $attributes);

        $existingPrimary = $item->barcodes()->where('is_primary', true)->first();

        if ($barcode !== null) {
            if ($existingPrimary === null) {
                if (ItemBarcode::where('barcode', $barcode)->where('item_id', '!=', $item->id)->exists()) {
                    $warnings[] = "barcode \"{$barcode}\" is already in use by another item, primary barcode not set";
                } else {
                    ItemBarcode::create(['item_id' => $item->id, 'barcode' => $barcode, 'type' => 'code128', 'is_primary' => true]);
                }
            } elseif ($existingPrimary->barcode !== $barcode) {
                $warnings[] = "barcode column (\"{$barcode}\") differs from the item's existing primary barcode (\"{$existingPrimary->barcode}\"); left unchanged";
            }
        } elseif ($existingPrimary === null) {
            app(AssignItemBarcodeAction::class)->execute($item);
        }

        $isNew ? $this->createdCount++ : $this->updatedCount++;

        if ($warnings !== []) {
            $this->results[] = [
                'row' => $rowNumber,
                'message' => implode('; ', $warnings),
                'level' => 'warning',
            ];
        }
    }

    public function render()
    {
        return view('livewire.catalog.items.import');
    }
}
