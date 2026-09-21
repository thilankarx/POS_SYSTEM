<?php

declare(strict_types=1);

namespace App\Livewire\Loyalty\Packages;

use App\Domain\Crm\Models\Customer;
use App\Domain\Loyalty\Models\LoyaltyPackage;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $active = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingActive(): void
    {
        $this->resetPage();
    }

    public function delete(int $id): void
    {
        $package = LoyaltyPackage::findOrFail($id);

        Gate::authorize('delete', $package);

        $package->delete();

        session()->flash('status', 'Loyalty package deleted.');
    }

    public function render()
    {
        Gate::authorize('viewAny', LoyaltyPackage::class);

        $stats = [
            'total' => LoyaltyPackage::count(),
            'active' => LoyaltyPackage::where('is_active', true)->count(),
            'members' => Customer::whereNotNull('loyalty_package_id')->count(),
        ];

        $packages = LoyaltyPackage::query()
            ->withCount('customers')
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->when($this->active !== '', fn ($q) => $q->where('is_active', $this->active === '1'))
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.loyalty.packages.index', ['packages' => $packages, 'stats' => $stats]);
    }
}
