<?php

declare(strict_types=1);

namespace App\Livewire\Reporting;

use App\Domain\Catalog\Models\Item;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Reporting\Queries\InventoryReportQuery;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Inventory extends Component
{
    use WithPagination;

    public string $from;

    public string $to;

    public ?int $item_id = null;

    public ?int $stock_location_id = null;

    public string $reason = '';

    public function mount(): void
    {
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->toDateString();
    }

    public function updatingFrom(): void
    {
        $this->resetPage();
    }

    public function updatingTo(): void
    {
        $this->resetPage();
    }

    public function updatingItemId(): void
    {
        $this->resetPage();
    }

    public function updatingStockLocationId(): void
    {
        $this->resetPage();
    }

    public function updatingReason(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        Gate::authorize('reports.inventory');

        $from = Carbon::parse($this->from)->startOfDay();
        $to = Carbon::parse($this->to)->endOfDay();
        $query = app(InventoryReportQuery::class);

        return view('livewire.reporting.inventory', [
            'movements' => $query->movements($from, $to, $this->item_id ?: null, $this->stock_location_id ?: null, $this->reason ?: null),
            'summaryByReason' => $query->summaryByReason($from, $to, $this->stock_location_id ?: null),
            'items' => Item::active()->orderBy('name')->get(),
            'stockLocations' => StockLocation::orderBy('name')->get(),
        ]);
    }
}
