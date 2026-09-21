<?php

declare(strict_types=1);

namespace App\Livewire\Catalog\Items;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Item;
use App\Domain\Crm\Models\Supplier;
use App\Domain\Taxation\Models\TaxCategory;
use App\Settings\BusinessProfileSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $category = '';

    #[Url(except: '')]
    public string $supplier = '';

    #[Url(as: 'type', except: '')]
    public string $stockType = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: 'name')]
    public string $sort = 'name';

    /** Business type to scope the list to; defaults to the configured store type, 'all' shows every type. */
    public string $businessType = '';

    public function mount(): void
    {
        $this->businessType = app(BusinessProfileSettings::class)->business_type;
    }

    /** @var array<int, int> */
    public array $selected = [];

    public bool $bulkCategoryApply = false;

    public ?int $bulkCategoryId = null;

    public bool $bulkSupplierApply = false;

    public ?int $bulkSupplierId = null;

    public bool $bulkTaxCategoryApply = false;

    public ?int $bulkTaxCategoryId = null;

    public bool $bulkActiveApply = false;

    public bool $bulkActiveValue = true;

    public ?string $bulkEditStatus = null;

    public function updatingSearch(): void
    {
        $this->resetListing();
    }

    public function updatingCategory(): void
    {
        $this->resetListing();
    }

    public function updatingSupplier(): void
    {
        $this->resetListing();
    }

    public function updatingStockType(): void
    {
        $this->resetListing();
    }

    public function updatingStatus(): void
    {
        $this->resetListing();
    }

    public function updatingSort(): void
    {
        $this->resetListing();
    }

    public function updatingBusinessType(): void
    {
        $this->resetListing();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->category = '';
        $this->supplier = '';
        $this->stockType = '';
        $this->status = '';
        $this->sort = 'name';
        $this->businessType = app(BusinessProfileSettings::class)->business_type;
        $this->resetListing();
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    public function delete(int $id): void
    {
        $item = Item::findOrFail($id);

        Gate::authorize('delete', $item);

        $item->delete();

        $this->selected = array_values(array_filter(
            $this->selected,
            fn (int|string $selectedId) => (int) $selectedId !== $item->id,
        ));

        session()->flash('status', 'Item deleted.');
    }

    public function applyBulkEdit(): void
    {
        Gate::authorize('items.bulk_edit');

        if ($this->selected === []) {
            $this->addError('bulkEdit', 'Select at least one item first.');

            return;
        }

        $updates = [];

        if ($this->bulkCategoryApply) {
            $updates['category_id'] = $this->bulkCategoryId;
        }

        if ($this->bulkSupplierApply) {
            $updates['supplier_id'] = $this->bulkSupplierId;
        }

        if ($this->bulkTaxCategoryApply) {
            $updates['tax_category_id'] = $this->bulkTaxCategoryId;
        }

        if ($this->bulkActiveApply) {
            $updates['is_active'] = $this->bulkActiveValue;
        }

        if ($updates === []) {
            $this->addError('bulkEdit', 'Choose at least one field to update.');

            return;
        }

        $count = Item::whereIn('id', $this->selected)->update($updates);

        $this->reset(['selected', 'bulkCategoryApply', 'bulkCategoryId', 'bulkSupplierApply', 'bulkSupplierId', 'bulkTaxCategoryApply', 'bulkTaxCategoryId', 'bulkActiveApply', 'bulkActiveValue']);

        $this->bulkEditStatus = "{$count} item(s) updated.";
    }

    private function resetListing(): void
    {
        $this->selected = [];
        $this->resetPage();
    }

    private function filteredQuery(): Builder
    {
        $search = trim($this->search);

        return Item::query()
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhereHas('barcodes', fn (Builder $query) => $query->where('barcode', 'like', "%{$search}%"));
                });
            })
            ->when($this->category !== '', fn (Builder $query) => $query->where('category_id', (int) $this->category))
            ->when($this->supplier !== '', fn (Builder $query) => $query->where('supplier_id', (int) $this->supplier))
            ->when($this->stockType !== '', fn (Builder $query) => $query->where('stock_type', $this->stockType))
            ->when($this->status === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($this->status === 'inactive', fn (Builder $query) => $query->where('is_active', false))
            ->forBusinessType($this->businessType === 'all' ? null : $this->businessType);
    }

    public function render(): View
    {
        Gate::authorize('viewAny', Item::class);

        $query = $this->filteredQuery()
            ->withHasMultiplePrices()
            ->withCurrentPrice()
            ->with(['category', 'supplier'])
            ->withSum('stockLevels as stock_on_hand', 'quantity');

        match ($this->sort) {
            'sku' => $query->orderBy('sku'),
            'newest' => $query->latest(),
            default => $query->orderBy('name'),
        };

        $items = $query->paginate(20);
        $summaryQuery = $this->filteredQuery();

        return view('livewire.catalog.items.index', [
            'items' => $items,
            'categories' => Category::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('company_name')->get(),
            'taxCategories' => TaxCategory::orderBy('name')->get(),
            'businessTypes' => BusinessProfileSettings::businessTypes(),
            'defaultBusinessType' => app(BusinessProfileSettings::class)->business_type,
            'summary' => [
                'total' => (clone $summaryQuery)->count(),
                'active' => (clone $summaryQuery)->where('is_active', true)->count(),
                'stocked' => (clone $summaryQuery)->where('stock_type', Item::STOCK_TYPE_STOCKED)->count(),
                'services' => (clone $summaryQuery)->where('stock_type', Item::STOCK_TYPE_SERVICE)->count(),
            ],
        ]);
    }
}
