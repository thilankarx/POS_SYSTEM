<?php

declare(strict_types=1);

namespace App\Livewire\Taxation\Categories;

use App\Domain\Taxation\Models\TaxCategory;
use App\Domain\Taxation\Models\TaxRate;
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
        $taxCategory = TaxCategory::findOrFail($id);

        Gate::authorize('delete', $taxCategory);

        $taxCategory->delete();

        session()->flash('status', 'Tax category deleted.');
    }

    public function render()
    {
        Gate::authorize('viewAny', TaxCategory::class);

        $taxCategories = TaxCategory::query()
            ->with(['rates' => fn ($q) => $q->orderBy('cascade_sequence')])
            ->withCount('rates')
            ->when(trim($this->search) !== '', function ($q) {
                $term = trim($this->search);
                $q->where(fn ($query) => $query->where('name', 'like', "%{$term}%")
                    ->orWhere('code', 'like', "%{$term}%")
                    ->orWhereHas('rates', fn ($rates) => $rates->where('name', 'like', "%{$term}%")));
            })
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.taxation.categories.index', [
            'taxCategories' => $taxCategories,
            'totalCategories' => TaxCategory::count(),
            'defaultCategories' => TaxCategory::where('is_default', true)->count(),
            'totalRates' => TaxRate::whereHas('taxCategory')->count(),
        ]);
    }
}
