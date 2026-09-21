<?php

declare(strict_types=1);

use App\Domain\Finance\Models\ExpenseCategory;
use App\Domain\Identity\Models\User;
use App\Livewire\Finance\ExpenseCategories\Form;
use App\Livewire\Finance\ExpenseCategories\Index;

beforeEach(function () {
    $this->seed();
    $this->owner = User::where('username', 'admin')->firstOrFail();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
});

it('lists and creates expense categories', function () {
    $this->actingAs($this->owner)
        ->get(route('expense-categories.index'))
        ->assertOk();

    Livewire\Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('name', 'Utilities')
        ->set('code', 'UTIL')
        ->call('save')
        ->assertRedirect(route('expense-categories.index'));

    expect(ExpenseCategory::where('name', 'Utilities')->where('code', 'UTIL')->exists())->toBeTrue();
});

it('edits an expense category', function () {
    $category = ExpenseCategory::create(['name' => 'Rent', 'code' => 'RENT-X']);

    Livewire\Livewire::actingAs($this->owner)
        ->test(Form::class, ['expenseCategory' => $category])
        ->set('name', 'Rent & Lease')
        ->call('save')
        ->assertRedirect(route('expense-categories.index'));

    expect($category->fresh()->name)->toBe('Rent & Lease');
});

it('rejects a duplicate code', function () {
    ExpenseCategory::create(['name' => 'Rent', 'code' => 'RENT-X']);

    Livewire\Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('name', 'Other')
        ->set('code', 'RENT-X')
        ->call('save')
        ->assertHasErrors('code');
});

it('blocks users without expenses.manage from creating expense categories', function () {
    $this->actingAs($this->cashier)
        ->get(route('expense-categories.create'))
        ->assertForbidden();
});

it('soft-deletes an expense category', function () {
    $category = ExpenseCategory::create(['name' => 'Rent', 'code' => 'RENT-X']);

    Livewire\Livewire::actingAs($this->owner)
        ->test(Index::class)
        ->call('delete', $category->id);

    expect(ExpenseCategory::find($category->id))->toBeNull()
        ->and(ExpenseCategory::withTrashed()->find($category->id))->not->toBeNull();
});
