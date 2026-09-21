<?php

declare(strict_types=1);

namespace App\Livewire\Finance\Expenses;

use App\Domain\Crm\Models\Supplier;
use App\Domain\Finance\Models\Expense;
use App\Domain\Finance\Models\ExpenseCategory;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Sales\Models\PaymentMethod;
use App\Support\Money\Money;
use App\Support\Money\Rules\ValidMoneyAmount;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Form extends Component
{
    public ?Expense $expense = null;

    public ?int $expense_category_id = null;

    public ?int $supplier_id = null;

    public ?int $stock_location_id = null;

    public ?int $payment_method_id = null;

    public string $amount = '';

    public string $tax_amount = '0.00';

    public string $reference = '';

    public string $description = '';

    public string $spent_on = '';

    public function mount(?Expense $expense = null): void
    {
        $this->expense = $expense;
        $this->spent_on = now()->toDateString();

        if ($expense !== null) {
            Gate::authorize('update', $expense);

            $this->expense_category_id = $expense->expense_category_id;
            $this->supplier_id = $expense->supplier_id;
            $this->stock_location_id = $expense->stock_location_id;
            $this->payment_method_id = $expense->payment_method_id;
            $this->amount = (string) ($expense->amount ?? Money::zero())->getAmount();
            $this->tax_amount = (string) ($expense->tax_amount ?? Money::zero())->getAmount();
            $this->reference = (string) $expense->reference;
            $this->description = (string) $expense->description;
            $this->spent_on = $expense->spent_on->toDateString();
        } else {
            Gate::authorize('create', Expense::class);
        }
    }

    public function rules(): array
    {
        return [
            'expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'stock_location_id' => ['nullable', 'integer', 'exists:stock_locations,id'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'amount' => ['required', new ValidMoneyAmount],
            'tax_amount' => ['required', new ValidMoneyAmount],
            'reference' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'spent_on' => ['required', 'date'],
        ];
    }

    public function save(): void
    {
        $validated = $this->validate();

        if ($this->expense !== null) {
            Gate::authorize('update', $this->expense);
            $this->expense->update($validated);
        } else {
            Gate::authorize('create', Expense::class);
            Expense::create([
                ...$validated,
                'user_id' => auth()->id(),
                'currency' => Money::currency(),
            ]);
        }

        session()->flash('status', 'Expense saved.');
        $this->redirectRoute('expenses.index');
    }

    public function render()
    {
        return view('livewire.finance.expenses.form', [
            'expenseCategories' => ExpenseCategory::orderBy('name')->get(),
            'suppliers' => Supplier::orderBy('company_name')->get(),
            'stockLocations' => StockLocation::orderBy('name')->get(),
            'paymentMethods' => PaymentMethod::orderBy('name')->get(),
        ]);
    }
}
