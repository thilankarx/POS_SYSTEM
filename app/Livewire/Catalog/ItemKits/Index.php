<?php

declare(strict_types=1);

namespace App\Livewire\Catalog\ItemKits;

use App\Domain\Catalog\Models\ItemKit;
use App\Settings\BusinessProfileSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
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

    #[Url(as: 'pricing', except: '')]
    public string $priceOption = '';

    #[Url(except: '')]
    public string $discount = '';

    #[Url(as: 'receipt', except: '')]
    public string $printOption = '';

    #[Url(except: 'name')]
    public string $sort = 'name';

    public string $businessType = '';

    public function mount(): void
    {
        $this->businessType = app(BusinessProfileSettings::class)->business_type;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingPriceOption(): void
    {
        $this->resetPage();
    }

    public function updatingDiscount(): void
    {
        $this->resetPage();
    }

    public function updatingPrintOption(): void
    {
        $this->resetPage();
    }

    public function updatingSort(): void
    {
        $this->resetPage();
    }

    public function updatingBusinessType(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'priceOption', 'discount', 'printOption']);
        $this->sort = 'name';
        $this->businessType = app(BusinessProfileSettings::class)->business_type;
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $itemKit = ItemKit::findOrFail($id);

        Gate::authorize('delete', $itemKit);

        $itemKit->delete();

        session()->flash('status', 'Item kit deleted.');
    }

    private function filteredQuery(): Builder
    {
        $search = trim($this->search);

        return ItemKit::query()
            ->when($search !== '', function (Builder $query) use ($search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('kit_number', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('items', fn (Builder $query) => $query
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%"));
                });
            })
            ->when($this->priceOption !== '', fn (Builder $query) => $query->where('price_option', $this->priceOption))
            ->when($this->discount === 'discounted', fn (Builder $query) => $query->where('price_option', '!=', 'components')->where('discount_value', '>', 0))
            ->when($this->discount === 'none', fn (Builder $query) => $query->where(function (Builder $query) {
                $query->where('price_option', 'components')->orWhere('discount_value', '<=', 0);
            }))
            ->when($this->printOption !== '', fn (Builder $query) => $query->where('print_option', $this->printOption))
            ->forBusinessType($this->businessType === 'all' ? null : $this->businessType);
    }

    public function render(): View
    {
        Gate::authorize('viewAny', ItemKit::class);

        $query = $this->filteredQuery()->withCount('items');

        match ($this->sort) {
            'number' => $query->orderBy('kit_number'),
            'components' => $query->orderByDesc('items_count')->orderBy('name'),
            'newest' => $query->latest(),
            default => $query->orderBy('name'),
        };

        $itemKits = $query->paginate(20);
        $summaryQuery = $this->filteredQuery();

        return view('livewire.catalog.item-kits.index', [
            'itemKits' => $itemKits,
            'businessTypes' => BusinessProfileSettings::businessTypes(),
            'defaultBusinessType' => app(BusinessProfileSettings::class)->business_type,
            'summary' => [
                'total' => (clone $summaryQuery)->count(),
                'components' => DB::table('item_kit_items')->whereIn('item_kit_id', (clone $summaryQuery)->select('item_kits.id'))->count(),
                'discounted' => (clone $summaryQuery)->where('price_option', '!=', 'components')->where('discount_value', '>', 0)->count(),
                'kitOnly' => (clone $summaryQuery)->where('print_option', 'kit_only')->count(),
            ],
        ]);
    }
}
