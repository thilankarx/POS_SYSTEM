<?php

declare(strict_types=1);

namespace App\Livewire\Finance\ExpenseCategories;

use App\Domain\Finance\Models\ExpenseCategory;
use App\Domain\Finance\Models\Expense;
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
        $expenseCategory = ExpenseCategory::findOrFail($id);

        Gate::authorize('delete', $expenseCategory);

        $expenseCategory->delete();

        session()->flash('status', 'Expense category deleted.');
    }

    public function render()
    {
        Gate::authorize('viewAny', ExpenseCategory::class);

        $stats = [
            'total' => ExpenseCategory::count(),
            'used' => ExpenseCategory::has('expenses')->count(),
            'expenses' => Expense::count(),
        ];

        $expenseCategories = ExpenseCategory::query()
            ->withCount('expenses')
            ->when($this->search !== '', fn ($q) => $q->where(fn ($query) => $query
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('code', 'like', "%{$this->search}%")))
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.finance.expense-categories.index', ['expenseCategories' => $expenseCategories, 'stats' => $stats]);
    }
}
