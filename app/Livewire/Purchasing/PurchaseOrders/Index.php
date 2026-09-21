<?php

declare(strict_types=1);

namespace App\Livewire\Purchasing\PurchaseOrders;

use App\Domain\Crm\Models\Supplier;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Purchasing\Models\PurchaseOrder;
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
    public string $status = '';

    #[Url(except: '')]
    public string $supplier = '';

    #[Url(except: '')]
    public string $location = '';

    #[Url(except: '')]
    public string $timing = '';

    #[Url(except: 'newest')]
    public string $sort = 'newest';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingSupplier(): void
    {
        $this->resetPage();
    }

    public function updatingLocation(): void
    {
        $this->resetPage();
    }

    public function updatingTiming(): void
    {
        $this->resetPage();
    }

    public function updatingSort(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status', 'supplier', 'location', 'timing', 'sort']);
        $this->sort = 'newest';
        $this->resetPage();
    }

    public function render()
    {
        Gate::authorize('viewAny', PurchaseOrder::class);

        $openStatuses = [
            PurchaseOrder::STATUS_DRAFT,
            PurchaseOrder::STATUS_SUBMITTED,
            PurchaseOrder::STATUS_APPROVED,
            PurchaseOrder::STATUS_PARTIALLY_RECEIVED,
        ];

        $query = PurchaseOrder::query()
            ->with([
                'supplier',
                'stockLocation',
                'lines:id,purchase_order_id,quantity_ordered,quantity_received',
            ])
            ->withCount('receivings')
            ->when(trim($this->search) !== '', function ($query) {
                $search = trim($this->search);

                $query->where(function ($query) use ($search) {
                    $query->where('number', 'like', "%{$search}%")
                        ->orWhere('note', 'like', "%{$search}%")
                        ->orWhereHas('supplier', fn ($query) => $query->where('company_name', 'like', "%{$search}%"));
                });
            })
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->supplier !== '', fn ($q) => $q->where('supplier_id', $this->supplier))
            ->when($this->location !== '', fn ($q) => $q->where('stock_location_id', $this->location))
            ->when($this->timing === 'overdue', fn ($q) => $q
                ->whereIn('status', $openStatuses)
                ->whereDate('expected_on', '<', today()))
            ->when($this->timing === 'due_soon', fn ($q) => $q
                ->whereIn('status', $openStatuses)
                ->whereBetween('expected_on', [today(), today()->addDays(7)]))
            ->when($this->timing === 'unscheduled', fn ($q) => $q->whereNull('expected_on'));

        match ($this->sort) {
            'expected' => $query->orderByRaw('expected_on IS NULL, expected_on ASC')->latest('id'),
            'total_desc' => $query->orderByDesc('total')->latest('id'),
            default => $query->latest(),
        };

        $purchaseOrders = $query->paginate(20);

        $statusCounts = PurchaseOrder::query()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $summary = [
            'total' => $statusCounts->sum(),
            'awaitingApproval' => (int) ($statusCounts[PurchaseOrder::STATUS_SUBMITTED] ?? 0),
            'toReceive' => (int) ($statusCounts[PurchaseOrder::STATUS_APPROVED] ?? 0)
                + (int) ($statusCounts[PurchaseOrder::STATUS_PARTIALLY_RECEIVED] ?? 0),
            'overdue' => PurchaseOrder::query()
                ->whereIn('status', $openStatuses)
                ->whereDate('expected_on', '<', today())
                ->count(),
        ];

        return view('livewire.purchasing.purchase-orders.index', [
            'purchaseOrders' => $purchaseOrders,
            'suppliers' => Supplier::orderBy('company_name')->get(['id', 'company_name']),
            'stockLocations' => StockLocation::orderBy('name')->get(['id', 'name']),
            'summary' => $summary,
        ]);
    }
}
