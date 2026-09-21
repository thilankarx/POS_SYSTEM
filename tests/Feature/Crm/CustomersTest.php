<?php

declare(strict_types=1);

use App\Domain\Crm\Models\Customer;
use App\Domain\Identity\Models\User;
use App\Livewire\Crm\Customers\Form;
use App\Livewire\Crm\Customers\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->owner = User::where('username', 'admin')->firstOrFail();
    $this->stockClerk = User::factory()->create();
    $this->stockClerk->assignRole('Stock Clerk');
});

it('lists and creates customers', function () {
    $this->actingAs($this->owner)
        ->get(route('customers.index'))
        ->assertOk();

    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('first_name', 'Alex')
        ->set('last_name', 'Rivera')
        ->set('account_number', 'CUST-001')
        ->set('credit_limit', '250.00')
        ->call('save')
        ->assertRedirect(route('customers.index'));

    expect(Customer::where('account_number', 'CUST-001')->exists())->toBeTrue();
});

it('edits a customer', function () {
    $customer = Customer::first();

    Livewire::actingAs($this->owner)
        ->test(Form::class, ['customer' => $customer])
        ->set('first_name', 'Renamed')
        ->call('save')
        ->assertRedirect(route('customers.index'));

    expect($customer->fresh()->person->first_name)->toBe('Renamed');
});

it('rejects a duplicate account number', function () {
    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('first_name', 'Alex')
        ->set('last_name', 'Rivera')
        ->set('account_number', 'DUP-CUST')
        ->call('save')
        ->assertRedirect(route('customers.index'));

    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('first_name', 'Sam')
        ->set('last_name', 'Lee')
        ->set('account_number', 'DUP-CUST')
        ->call('save')
        ->assertHasErrors('account_number');
});

it('lets two customers save with no account number, instead of colliding on an empty string', function () {
    // Regression: account_number is nullable+unique, but Laravel's `unique`
    // rule isn't "implicit" so it never runs against an empty string --
    // blank input sailed through validation untouched and only the DB's
    // unique index ever caught the second one, as an uncaught
    // UniqueConstraintViolationException (a raw 409, not a field error).
    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('first_name', 'No')
        ->set('last_name', 'Account')
        ->call('save')
        ->assertRedirect(route('customers.index'));

    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('first_name', 'Also')
        ->set('last_name', 'NoAccount')
        ->call('save')
        ->assertRedirect(route('customers.index'));

    expect(Customer::whereHas('person', fn ($q) => $q->where('first_name', 'No'))->firstOrFail()->account_number)->toBeNull()
        ->and(Customer::whereHas('person', fn ($q) => $q->where('first_name', 'Also'))->firstOrFail()->account_number)->toBeNull();
});

it('rejects a non-numeric credit limit', function () {
    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('first_name', 'Alex')
        ->set('last_name', 'Rivera')
        ->set('credit_limit', 'not-a-number')
        ->call('save')
        ->assertHasErrors('credit_limit');
});

it('allows is_tax_exempt without a tax_category_id', function () {
    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('first_name', 'Alex')
        ->set('last_name', 'Rivera')
        ->set('is_tax_exempt', true)
        ->call('save')
        ->assertHasNoErrors();
});

it('blocks users without customers.view from the customers section', function () {
    $this->actingAs($this->stockClerk)
        ->get(route('customers.index'))
        ->assertForbidden();
});

it('soft-deletes a customer', function () {
    $customer = Customer::first();

    Livewire::actingAs($this->owner)
        ->test(Index::class)
        ->call('delete', $customer->id);

    expect(Customer::find($customer->id))->toBeNull()
        ->and(Customer::withTrashed()->find($customer->id))->not->toBeNull();
});
