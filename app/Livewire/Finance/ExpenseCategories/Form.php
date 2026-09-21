<?php

declare(strict_types=1);

namespace App\Livewire\Finance\ExpenseCategories;

use App\Domain\Finance\Models\ExpenseCategory;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Form extends Component
{
    public ?ExpenseCategory $expenseCategory = null;

    public string $name = '';

    public string $code = '';

    public string $description = '';

    public function mount(?ExpenseCategory $expenseCategory = null): void
    {
        $this->expenseCategory = $expenseCategory;

        if ($expenseCategory !== null) {
            Gate::authorize('update', $expenseCategory);

            $this->name = $expenseCategory->name;
            $this->code = $expenseCategory->code;
            $this->description = (string) $expenseCategory->description;
        } else {
            Gate::authorize('create', ExpenseCategory::class);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:32', Rule::unique('expense_categories', 'code')->ignore($this->expenseCategory?->id)],
            'description' => ['nullable', 'string'],
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        if ($this->expenseCategory !== null) {
            Gate::authorize('update', $this->expenseCategory);
            $this->expenseCategory->update($validated);
        } else {
            Gate::authorize('create', ExpenseCategory::class);
            ExpenseCategory::create($validated);
        }

        session()->flash('status', 'Expense category saved.');
        $this->redirectRoute('expense-categories.index');
    }

    public function render()
    {
        return view('livewire.finance.expense-categories.form');
    }
}
