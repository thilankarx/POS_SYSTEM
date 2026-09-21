<?php

declare(strict_types=1);

namespace App\Livewire\Inventory\StockLocations;

use App\Domain\Inventory\Models\StockLocation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class StockLevels extends Component
{
    use WithPagination;

    public StockLocation $stockLocation;

    public string $search = '';

    public string $availability = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingAvailability(): void
    {
        $this->resetPage();
    }

    public function mount(StockLocation $stockLocation): void
    {
        $this->stockLocation = $stockLocation;

        Gate::authorize('viewStock', $stockLocation);
    }

    public function render()
    {
        $baseQuery = $this->stockLocation->stockLevels()
            ->with('item')
            ->whereHas('item')
            ->when($this->search !== '', fn (Builder $query) => $query->whereHas('item', fn (Builder $itemQuery) => $itemQuery
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('sku', 'like', "%{$this->search}%")))
            ->when($this->availability === 'in_stock', fn (Builder $query) => $query->where('quantity', '>', 0))
            ->when($this->availability === 'out_of_stock', fn (Builder $query) => $query->where('quantity', '<=', 0))
            ->when($this->availability === 'low_stock', fn (Builder $query) => $query
                ->where('quantity', '>', 0)
                ->whereHas('item', fn (Builder $itemQuery) => $itemQuery->whereColumn('stock_levels.quantity', '<=', 'items.reorder_level')));

        $summary = $this->stockLocation->stockLevels()
            ->whereHas('item')
            ->selectRaw('COALESCE(SUM(quantity), 0) as on_hand')
            ->selectRaw('COALESCE(SUM(reserved_quantity), 0) as reserved')
            ->selectRaw('SUM(CASE WHEN quantity > 0 THEN 1 ELSE 0 END) as stocked_items')
            ->selectRaw('SUM(CASE WHEN quantity <= 0 THEN 1 ELSE 0 END) as out_of_stock_items')
            ->first();

        $lowStockCount = $this->stockLocation->stockLevels()
            ->where('quantity', '>', 0)
            ->whereHas('item', fn (Builder $query) => $query->whereColumn('stock_levels.quantity', '<=', 'items.reorder_level'))
            ->count();

        $levels = $baseQuery
            ->orderBy('quantity')
            ->paginate(20);

        return view('livewire.inventory.stock-locations.stock-levels', [
            'levels' => $levels,
            'summary' => [
                'on_hand' => (float) ($summary->on_hand ?? 0),
                'reserved' => (float) ($summary->reserved ?? 0),
                'available' => (float) ($summary->on_hand ?? 0) - (float) ($summary->reserved ?? 0),
                'stocked_items' => (int) ($summary->stocked_items ?? 0),
                'out_of_stock' => (int) ($summary->out_of_stock_items ?? 0),
                'low_stock' => $lowStockCount,
            ],
        ]);
    }
}
