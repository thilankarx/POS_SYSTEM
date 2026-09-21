<?php

declare(strict_types=1);

use App\Domain\Crm\Models\Customer;
use App\Domain\Identity\Models\Person;
use App\Domain\Identity\Models\User;
use App\Domain\Loyalty\Models\LoyaltyPackage;
use App\Livewire\Crm\Customers\Form as CustomerForm;
use App\Livewire\Loyalty\Packages\Form as PackageForm;
use App\Livewire\Loyalty\Packages\Index as PackagesIndex;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->admin = User::where('username', 'admin')->firstOrFail();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
});

it('lets a Cashier view loyalty packages but not create one', function () {
    $this->actingAs($this->cashier)->get(route('loyalty-packages.index'))->assertOk();
    $this->actingAs($this->cashier)->get(route('loyalty-packages.create'))->assertForbidden();
});

it('lets an admin create and edit a loyalty package', function () {
    Livewire::actingAs($this->admin)
        ->test(PackageForm::class)
        ->set('name', 'Gold')
        ->set('points_per_currency_unit', '2')
        ->set('currency_value_per_point', '0.02')
        ->call('save')
        ->assertHasNoErrors();

    expect(LoyaltyPackage::where('name', 'Gold')->exists())->toBeTrue();
});

it('saves a loyalty package expiry window, and leaves it null when blank', function () {
    Livewire::actingAs($this->admin)
        ->test(PackageForm::class)
        ->set('name', 'Expiring Gold')
        ->set('points_per_currency_unit', '2')
        ->set('currency_value_per_point', '0.02')
        ->set('points_expire_after_days', '365')
        ->call('save')
        ->assertHasNoErrors();

    expect(LoyaltyPackage::where('name', 'Expiring Gold')->firstOrFail()->points_expire_after_days)->toBe(365);

    Livewire::actingAs($this->admin)
        ->test(PackageForm::class)
        ->set('name', 'Never Expires')
        ->set('points_per_currency_unit', '2')
        ->set('currency_value_per_point', '0.02')
        ->call('save')
        ->assertHasNoErrors();

    expect(LoyaltyPackage::where('name', 'Never Expires')->firstOrFail()->points_expire_after_days)->toBeNull();
});

it('rejects a non-integer or zero expiry window', function () {
    Livewire::actingAs($this->admin)
        ->test(PackageForm::class)
        ->set('name', 'Bad Window')
        ->set('points_per_currency_unit', '2')
        ->set('currency_value_per_point', '0.02')
        ->set('points_expire_after_days', '0')
        ->call('save')
        ->assertHasErrors('points_expire_after_days');
});

it('lets a Cashier see a customer points balance but not adjust it', function () {
    $person = Person::create(['first_name' => 'Pat', 'last_name' => 'Points', 'email' => 'pat@example.test']);
    $customer = Customer::create(['person_id' => $person->id, 'points_balance' => '25'])->refresh();

    $this->actingAs($this->cashier)->get(route('customers.edit', $customer))->assertOk();

    Livewire::actingAs($this->cashier)
        ->test(CustomerForm::class, ['customer' => $customer])
        ->set('pointsAdjustment', '10')
        ->call('adjustPoints')
        ->assertForbidden();

    Livewire::actingAs($this->admin)
        ->test(CustomerForm::class, ['customer' => $customer])
        ->set('pointsAdjustment', '10')
        ->call('adjustPoints')
        ->assertHasNoErrors();

    expect((string) $customer->fresh()->points_balance)->toBe('35.000');
});

it('lets an admin delete a loyalty package', function () {
    $package = LoyaltyPackage::create([
        'name' => 'To delete',
        'points_per_currency_unit' => '1',
        'currency_value_per_point' => '0.01',
        'is_active' => true,
    ]);

    Livewire::actingAs($this->admin)
        ->test(PackagesIndex::class)
        ->call('delete', $package->id);

    expect(LoyaltyPackage::find($package->id))->toBeNull()
        ->and(LoyaltyPackage::withTrashed()->find($package->id))->not->toBeNull();
});

it('denies deleting a loyalty package to a Cashier', function () {
    $package = LoyaltyPackage::create([
        'name' => 'Guarded',
        'points_per_currency_unit' => '1',
        'currency_value_per_point' => '0.01',
        'is_active' => true,
    ]);

    Livewire::actingAs($this->cashier)
        ->test(PackagesIndex::class)
        ->call('delete', $package->id)
        ->assertForbidden();
});
