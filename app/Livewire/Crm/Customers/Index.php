<?php

declare(strict_types=1);

namespace App\Livewire\Crm\Customers;

use App\Domain\Crm\Models\Customer;
use App\Domain\Sales\Models\Sale;
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
        $customer = Customer::findOrFail($id);

        Gate::authorize('delete', $customer);

        $customer->delete();

        session()->flash('status', 'Customer deleted.');
    }

    public function render()
    {
        Gate::authorize('viewAny', Customer::class);

        $stats = [
            'total' => Customer::count(),
            'loyalty' => Customer::whereNotNull('loyalty_package_id')->count(),
            'tax_exempt' => Customer::where('is_tax_exempt', true)->count(),
        ];

        $customers = Customer::query()
            ->with(['person', 'loyaltyPackage'])
            ->withCount(['sales as completed_sales_count' => fn (Builder $query) => $query->where('status', Sale::STATUS_COMPLETED)])
            ->when($this->search !== '', function (Builder $query) {
                $query->where(function (Builder $query) {
                    $query->where('account_number', 'like', "%{$this->search}%")
                        ->orWhere('company_name', 'like', "%{$this->search}%")
                        ->orWhereHas('person', fn (Builder $personQuery) => $personQuery
                            ->where('first_name', 'like', "%{$this->search}%")
                            ->orWhere('last_name', 'like', "%{$this->search}%")
                            ->orWhere('email', 'like', "%{$this->search}%")
                            ->orWhere('phone', 'like', "%{$this->search}%"));
                });
            })
            ->orderBy('company_name')
            ->orderBy('id')
            ->paginate(20);

        return view('livewire.crm.customers.index', ['customers' => $customers, 'stats' => $stats]);
    }
}
