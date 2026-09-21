<?php

declare(strict_types=1);

use App\Domain\Crm\Models\Supplier;
use App\Domain\Identity\Models\User;
use App\Livewire\Crm\Suppliers\Form;
use App\Livewire\Crm\Suppliers\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->owner = User::where('username', 'admin')->firstOrFail();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
});

it('lists and creates suppliers', function () {
    $this->actingAs($this->owner)
        ->get(route('suppliers.index'))
        ->assertOk();

    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('first_name', 'Jane')
        ->set('last_name', 'Doe')
        ->set('company_name', 'Acme Distribution')
        ->set('account_number', 'ACME-001')
        ->call('save')
        ->assertRedirect(route('suppliers.index'));

    expect(Supplier::where('company_name', 'Acme Distribution')->where('account_number', 'ACME-001')->exists())->toBeTrue();
});

it('edits a supplier', function () {
    $supplier = Supplier::first();

    Livewire::actingAs($this->owner)
        ->test(Form::class, ['supplier' => $supplier])
        ->set('company_name', 'Renamed Co')
        ->call('save')
        ->assertRedirect(route('suppliers.index'));

    expect($supplier->fresh()->company_name)->toBe('Renamed Co');
});

it('rejects a duplicate account number', function () {
    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('first_name', 'Jane')
        ->set('last_name', 'Doe')
        ->set('company_name', 'First Co')
        ->set('account_number', 'DUP-001')
        ->call('save')
        ->assertRedirect(route('suppliers.index'));

    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('first_name', 'John')
        ->set('last_name', 'Smith')
        ->set('company_name', 'Second Co')
        ->set('account_number', 'DUP-001')
        ->call('save')
        ->assertHasErrors('account_number');
});

it('lets two suppliers save with no account number, instead of colliding on an empty string', function () {
    // Regression: account_number is nullable+unique, but Laravel's `unique`
    // rule isn't "implicit" so it never runs against an empty string --
    // blank input sailed through validation untouched and only the DB's
    // unique index ever caught the second one, as an uncaught
    // UniqueConstraintViolationException (a raw 409, not a field error).
    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('first_name', 'Jane')
        ->set('last_name', 'Doe')
        ->set('company_name', 'No Account Co')
        ->call('save')
        ->assertRedirect(route('suppliers.index'));

    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('first_name', 'John')
        ->set('last_name', 'Smith')
        ->set('company_name', 'Also No Account Co')
        ->call('save')
        ->assertRedirect(route('suppliers.index'));

    expect(Supplier::where('company_name', 'No Account Co')->firstOrFail()->account_number)->toBeNull()
        ->and(Supplier::where('company_name', 'Also No Account Co')->firstOrFail()->account_number)->toBeNull();
});

it('validates the supplier_type enum', function () {
    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('first_name', 'Jane')
        ->set('last_name', 'Doe')
        ->set('company_name', 'Other Co')
        ->set('supplier_type', 'not-a-real-type')
        ->call('save')
        ->assertHasErrors('supplier_type');
});

it('blocks users without suppliers.manage from creating suppliers', function () {
    $this->actingAs($this->cashier)
        ->get(route('suppliers.create'))
        ->assertForbidden();
});

it('soft-deletes a supplier, gated on suppliers.manage since there is no dedicated delete ability', function () {
    $supplier = Supplier::first();

    Livewire::actingAs($this->owner)
        ->test(Index::class)
        ->call('delete', $supplier->id);

    expect(Supplier::find($supplier->id))->toBeNull()
        ->and(Supplier::withTrashed()->find($supplier->id))->not->toBeNull();
});
