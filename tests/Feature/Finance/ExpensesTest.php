<?php

declare(strict_types=1);

use App\Domain\Finance\Models\Expense;
use App\Domain\Finance\Models\ExpenseCategory;
use App\Domain\Identity\Models\User;
use App\Livewire\Finance\Expenses\Form;
use App\Livewire\Finance\Expenses\Index;

beforeEach(function () {
    $this->seed();
    $this->owner = User::where('username', 'admin')->firstOrFail();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
    $this->category = ExpenseCategory::create(['name' => 'Utilities', 'code' => 'UTIL-TEST']);
});

it('lists and creates expenses', function () {
    $this->actingAs($this->owner)
        ->get(route('expenses.index'))
        ->assertOk();

    Livewire\Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('expense_category_id', $this->category->id)
        ->set('amount', '150.00')
        ->set('spent_on', now()->toDateString())
        ->set('reference', 'INV-9001')
        ->call('save')
        ->assertRedirect(route('expenses.index'));

    $expense = Expense::where('reference', 'INV-9001')->firstOrFail();
    expect((string) $expense->amount->getAmount())->toBe('150.00')
        ->and($expense->user_id)->toBe($this->owner->id)
        ->and($expense->expense_category_id)->toBe($this->category->id);
});

it('edits an expense', function () {
    $expense = Expense::create([
        'expense_category_id' => $this->category->id,
        'user_id' => $this->owner->id,
        'amount' => '75.00',
        'currency' => 'USD',
        'spent_on' => now()->toDateString(),
    ]);

    Livewire\Livewire::actingAs($this->owner)
        ->test(Form::class, ['expense' => $expense])
        ->set('amount', '90.00')
        ->call('save')
        ->assertRedirect(route('expenses.index'));

    expect((string) $expense->fresh()->amount->getAmount())->toBe('90.00');
});

it('rejects an invalid amount', function () {
    Livewire\Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('expense_category_id', $this->category->id)
        ->set('amount', 'not-a-number')
        ->set('spent_on', now()->toDateString())
        ->call('save')
        ->assertHasErrors('amount');
});

it('blocks users without expenses.manage from creating expenses', function () {
    $this->actingAs($this->cashier)
        ->get(route('expenses.create'))
        ->assertForbidden();
});

it('blocks users without expenses.view from listing expenses', function () {
    $this->actingAs($this->cashier)
        ->get(route('expenses.index'))
        ->assertForbidden();
});

it('soft-deletes an expense', function () {
    $expense = Expense::create([
        'expense_category_id' => $this->category->id,
        'user_id' => $this->owner->id,
        'amount' => '40.00',
        'currency' => 'USD',
        'spent_on' => now()->toDateString(),
    ]);

    Livewire\Livewire::actingAs($this->owner)
        ->test(Index::class)
        ->call('delete', $expense->id);

    expect(Expense::find($expense->id))->toBeNull()
        ->and(Expense::withTrashed()->find($expense->id))->not->toBeNull();
});
