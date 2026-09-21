<?php

declare(strict_types=1);

namespace App\Livewire\Inventory\StockLocations;

use App\Domain\Inventory\Models\StockLocation;
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
        $stockLocation = StockLocation::findOrFail($id);

        Gate::authorize('delete', $stockLocation);

        $stockLocation->delete();

        session()->flash('status', 'Stock location deleted.');
    }

    public function render()
    {
        Gate::authorize('viewAny', StockLocation::class);

        $stockLocations = StockLocation::query()
            ->withSum('stockLevels as on_hand_quantity', 'quantity')
            ->withCount(['stockLevels as stocked_items_count' => fn ($query) => $query->where('quantity', '>', 0)])
            ->when($this->search !== '', fn ($query) => $query->where(function ($query) {
                $query->where('name', 'like', "%{$this->search}%")
                    ->orWhere('code', 'like', "%{$this->search}%");
            }))
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.inventory.stock-locations.index', [
            'stockLocations' => $stockLocations,
            'stats' => [
                'total' => StockLocation::count(),
                'selling' => StockLocation::where('sells', true)->count(),
                'receiving' => StockLocation::where('receives', true)->count(),
            ],
        ]);
    }
}
