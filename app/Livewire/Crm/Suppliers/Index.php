<?php

declare(strict_types=1);

namespace App\Livewire\Crm\Suppliers;

use App\Domain\Crm\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
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

    public function delete(int $id): void
    {
        $supplier = Supplier::findOrFail($id);

        Gate::authorize('delete', $supplier);

        $supplier->delete();

        session()->flash('status', 'Supplier deleted.');
    }

    public function render()
    {
        Gate::authorize('viewAny', Supplier::class);

        $stats = [
            'total' => Supplier::count(),
            'goods' => Supplier::where('supplier_type', 'goods')->count(),
            'expense' => Supplier::where('supplier_type', 'expense')->count(),
        ];

        $suppliers = Supplier::query()
            ->with('person')
            ->withCount('items')
            ->when($this->search !== '', fn (Builder $query) => $query->where(function (Builder $query) {
                $query->where('company_name', 'like', "%{$this->search}%")
                    ->orWhere('agency_name', 'like', "%{$this->search}%")
                    ->orWhere('account_number', 'like', "%{$this->search}%")
                    ->orWhereHas('person', fn (Builder $personQuery) => $personQuery
                        ->where('first_name', 'like', "%{$this->search}%")
                        ->orWhere('last_name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%")
                        ->orWhere('phone', 'like', "%{$this->search}%"));
            }))
            ->orderBy('company_name')
            ->paginate(20);

        return view('livewire.crm.suppliers.index', ['suppliers' => $suppliers, 'stats' => $stats]);
    }
}
