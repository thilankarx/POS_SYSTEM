<?php

declare(strict_types=1);

namespace App\Livewire\Inventory\SerialNumbers;

use App\Domain\Inventory\Models\SerialNumber;
use App\Domain\Inventory\Models\StockLocation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public string $stock_location_id = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingStockLocationId(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        Gate::authorize('inventory.view');

        $statusCounts = SerialNumber::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $serialNumbers = SerialNumber::query()
            ->with(['item', 'stockLocation', 'lot'])
            ->when($this->search !== '', fn (Builder $query) => $query->where(function (Builder $query) {
                $query->where('serial', 'like', "%{$this->search}%")
                    ->orWhereHas('item', fn (Builder $itemQuery) => $itemQuery
                        ->where('name', 'like', "%{$this->search}%")
                        ->orWhere('sku', 'like', "%{$this->search}%"))
                    ->orWhereHas('lot', fn (Builder $lotQuery) => $lotQuery->where('lot_number', 'like', "%{$this->search}%"));
            }))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->stock_location_id !== '', fn ($q) => $q->where('stock_location_id', $this->stock_location_id))
            ->latest()
            ->paginate(20);

        return view('livewire.inventory.serial-numbers.index', [
            'serialNumbers' => $serialNumbers,
            'stockLocations' => StockLocation::orderBy('name')->get(),
            'stats' => [
                'total' => $statusCounts->sum(),
                'in_stock' => (int) ($statusCounts[SerialNumber::STATUS_IN_STOCK] ?? 0),
                'sold' => (int) ($statusCounts[SerialNumber::STATUS_SOLD] ?? 0),
                'returned' => (int) ($statusCounts['returned'] ?? 0),
                'scrapped' => (int) ($statusCounts['scrapped'] ?? 0),
            ],
        ]);
    }
}
