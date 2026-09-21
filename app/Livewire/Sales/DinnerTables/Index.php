<?php

declare(strict_types=1);

namespace App\Livewire\Sales\DinnerTables;

use App\Domain\Sales\Models\DinnerTable;
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
        $table = DinnerTable::findOrFail($id);

        Gate::authorize('delete', $table);

        $table->delete();

        session()->flash('status', 'Dinner table deleted.');
    }

    public function render()
    {
        Gate::authorize('viewAny', DinnerTable::class);

        $tables = DinnerTable::query()
            ->with('stockLocation')
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.sales.dinner-tables.index', ['tables' => $tables]);
    }
}
