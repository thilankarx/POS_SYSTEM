<?php

declare(strict_types=1);

namespace App\Livewire\Finance\Expenses;

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
        $expense = Expense::findOrFail($id);

        Gate::authorize('delete', $expense);

        $expense->delete();

        session()->flash('status', 'Expense deleted.');
    }

    public function render()
    {
        Gate::authorize('viewAny', Expense::class);

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();
        $monthlyTotals = Expense::query()
            ->whereBetween('spent_on', [$monthStart, $monthEnd])
            ->selectRaw('currency, SUM(amount) as total_amount')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get();

        $expenses = Expense::query()
            ->with(['category', 'supplier'])
            ->when($this->search !== '', fn ($query) => $query->where(function ($query) {
                $query->where('reference', 'like', "%{$this->search}%")
                    ->orWhere('description', 'like', "%{$this->search}%")
                    ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', "%{$this->search}%"))
                    ->orWhereHas('supplier', fn ($supplierQuery) => $supplierQuery->where('company_name', 'like', "%{$this->search}%"));
            }))
            ->orderBy('spent_on', 'desc')
            ->paginate(20);

        return view('livewire.finance.expenses.index', [
            'expenses' => $expenses,
            'stats' => [
                'total' => Expense::count(),
                'this_month' => Expense::whereBetween('spent_on', [$monthStart, $monthEnd])->count(),
                'categories' => Expense::distinct()->count('expense_category_id'),
            ],
            'monthlyTotals' => $monthlyTotals,
        ]);
    }
}
