<?php

declare(strict_types=1);

namespace App\Livewire\Reporting;

use App\Domain\Crm\Models\Supplier;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Purchasing\Models\Receiving;
use App\Domain\Reporting\Queries\ReceivingReportQuery;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Receivings extends Component
{
    use WithPagination;

    public string $from;

    public string $to;

    public ?int $supplier_id = null;

    public ?int $stock_location_id = null;

    public string $type = '';

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

    public function updatingSupplierId(): void
    {
        $this->resetPage();
    }

    public function updatingStockLocationId(): void
    {
        $this->resetPage();
    }

    public function updatingType(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        Gate::authorize('reports.receivings');

        $from = Carbon::parse($this->from)->startOfDay();
        $to = Carbon::parse($this->to)->endOfDay();
        $query = app(ReceivingReportQuery::class);

        return view('livewire.reporting.receivings', [
            'receivings' => $query->receivings($from, $to, $this->supplier_id ?: null, $this->stock_location_id ?: null, $this->type ?: null),
            'summaryByType' => $query->summaryByType($from, $to, $this->stock_location_id ?: null),
            'suppliers' => Supplier::orderBy('company_name')->get(),
            'stockLocations' => StockLocation::orderBy('name')->get(),
            'types' => [
                Receiving::TYPE_RECEIPT => 'Receipt',
                Receiving::TYPE_RETURN_TO_SUPPLIER => 'Return to supplier',
                Receiving::TYPE_TRANSFER_IN => 'Transfer in',
                Receiving::TYPE_TRANSFER_OUT => 'Transfer out',
            ],
        ]);
    }
}
