<?php

declare(strict_types=1);

namespace App\Livewire\Inventory\StockCounts;

use App\Domain\Inventory\Models\StockCount;
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

    public string $status = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        Gate::authorize('viewAny', StockCount::class);

        $statusCounts = StockCount::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $stockCounts = StockCount::query()
            ->with('stockLocation')
            ->withCount('lines')
            ->withCount(['lines as counted_lines_count' => fn (Builder $query) => $query->whereNotNull('counted_quantity')])
            ->when($this->search !== '', fn (Builder $query) => $query->where(function (Builder $query) {
                $query->where('reference', 'like', "%{$this->search}%")
                    ->orWhereHas('stockLocation', fn (Builder $locationQuery) => $locationQuery->where('name', 'like', "%{$this->search}%"));
            }))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->latest()
            ->paginate(20);

        return view('livewire.inventory.stock-counts.index', [
            'stockCounts' => $stockCounts,
            'stats' => [
                'total' => $statusCounts->sum(),
                'open' => (int) ($statusCounts[StockCount::STATUS_DRAFT] ?? 0) + (int) ($statusCounts[StockCount::STATUS_COUNTING] ?? 0),
                'review' => (int) ($statusCounts[StockCount::STATUS_REVIEW] ?? 0),
                'approved' => (int) ($statusCounts[StockCount::STATUS_APPROVED] ?? 0),
            ],
        ]);
    }
}
