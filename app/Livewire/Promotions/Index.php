<?php

declare(strict_types=1);

namespace App\Livewire\Promotions;

use App\Domain\Promotions\Models\Promotion;
use Illuminate\Support\Carbon;
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
        $promotion = Promotion::findOrFail($id);

        Gate::authorize('delete', $promotion);

        $promotion->delete();

        session()->flash('status', 'Promotion deleted.');
    }

    public function render()
    {
        Gate::authorize('viewAny', Promotion::class);

        $now = Carbon::now();
        $totals = Promotion::query()
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('SUM(CASE WHEN is_active = 1 AND (starts_at IS NULL OR starts_at <= ?) AND (ends_at IS NULL OR ends_at >= ?) THEN 1 ELSE 0 END) as available_count', [$now, $now])
            ->selectRaw('SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_count')
            ->first();

        $promotions = Promotion::query()
            ->withCount('conditions')
            ->when($this->search !== '', fn ($q) => $q->where(function ($query) {
                $query->where('name', 'like', "%{$this->search}%")
                    ->orWhere('code', 'like', "%{$this->search}%");
            }))
            ->when($this->active !== '', fn ($q) => $q->where('is_active', $this->active === '1'))
            ->orderBy('priority', 'desc')
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.promotions.index', [
            'promotions' => $promotions,
            'stats' => [
                'total' => (int) ($totals->total_count ?? 0),
                'available' => (int) ($totals->available_count ?? 0),
                'inactive' => (int) ($totals->inactive_count ?? 0),
            ],
        ]);
    }
}
