<?php

declare(strict_types=1);

namespace App\Livewire\Purchasing\Receivings;

use App\Domain\Purchasing\Models\Receiving;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        Gate::authorize('viewAny', Receiving::class);

        $receivings = Receiving::query()
            ->with(['supplier', 'purchaseOrder', 'stockLocation', 'transferToLocation', 'user.person'])
            ->withCount('lines')
            ->when(trim($this->search) !== '', function ($q) {
                $term = trim($this->search);

                $q->where(function ($query) use ($term) {
                    $query->where('number', 'like', "%{$term}%")
                        ->orWhere('supplier_reference', 'like', "%{$term}%")
                        ->orWhereHas('supplier', fn ($supplier) => $supplier->where('company_name', 'like', "%{$term}%"))
                        ->orWhereHas('purchaseOrder', fn ($order) => $order->where('number', 'like', "%{$term}%"));
                });
            })
            ->latest('received_at')
            ->paginate(20);

        return view('livewire.purchasing.receivings.index', [
            'receivings' => $receivings,
            'totalReceivings' => Receiving::count(),
            'thisMonthReceivings' => Receiving::whereBetween('received_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'purchaseOrderReceivings' => Receiving::whereNotNull('purchase_order_id')->count(),
        ]);
    }
}
