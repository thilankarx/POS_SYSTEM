<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Taxation\Models\TaxCategory;
use App\Domain\Taxation\Models\TaxRate;
use App\Livewire\Taxation\Categories\Form;
use App\Livewire\Taxation\Categories\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();

    $this->admin = User::where('username', 'admin')->firstOrFail();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
});

it('denies the tax categories page to a Cashier but allows it for an admin', function () {
    $this->actingAs($this->cashier)->get(route('tax-categories.index'))->assertForbidden();
    $this->actingAs($this->admin)->get(route('tax-categories.index'))->assertOk();
});

it('lists and searches tax categories', function () {
    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('search', 'Standard')
        ->assertSee('Standard Rate');

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('search', 'no-such-category')
        ->assertDontSee('Standard Rate');
});

it('creates a tax category with two cascading rates', function () {
    Livewire::actingAs($this->admin)
        ->test(Form::class)
        ->set('name', 'Luxury')
        ->set('code', 'luxury')
        ->call('addRate')
        ->set('rates.0.name', 'State')
        ->set('rates.0.rate', '10.00')
        ->call('addRate')
        ->set('rates.1.name', 'Luxury surcharge')
        ->set('rates.1.rate', '5.00')
        ->call('save')
        ->assertHasNoErrors();

    $category = TaxCategory::where('code', 'luxury')->firstOrFail();
    $rates = $category->rates()->orderBy('cascade_sequence')->get();

    expect($rates)->toHaveCount(2)
        ->and($rates[0]->name)->toBe('State')
        ->and((string) $rates[0]->rate)->toBe('10.0000')
        ->and($rates[0]->cascade_sequence)->toBe(0)
        ->and($rates[1]->name)->toBe('Luxury surcharge')
        ->and($rates[1]->cascade_sequence)->toBe(1)
        ->and($rates[0]->tax_jurisdiction_id)->not->toBeNull();
});

it('creates a tax category with zero rates', function () {
    Livewire::actingAs($this->admin)
        ->test(Form::class)
        ->set('name', 'Exempt goods')
        ->set('code', 'exempt-goods')
        ->call('save')
        ->assertHasNoErrors();

    $category = TaxCategory::where('code', 'exempt-goods')->firstOrFail();

    expect($category->rates)->toBeEmpty();
});

it('edits a category: removes one rate, adds another, updates a third', function () {
    $category = TaxCategory::create(['name' => 'Mixed', 'code' => 'mixed', 'is_default' => false]);
    $rateToRemove = TaxRate::create(['tax_category_id' => $category->id, 'name' => 'Old', 'rate' => '1.00', 'rounding_mode' => 'half_up', 'cascade_sequence' => 0]);
    $rateToEdit = TaxRate::create(['tax_category_id' => $category->id, 'name' => 'Keep', 'rate' => '2.00', 'rounding_mode' => 'half_up', 'cascade_sequence' => 1]);

    Livewire::actingAs($this->admin)
        ->test(Form::class, ['taxCategory' => $category])
        ->assertSet('rates.0.name', 'Old')
        ->assertSet('rates.1.name', 'Keep')
        ->call('removeRate', 0)
        ->set('rates.0.rate', '2.50')
        ->call('addRate')
        ->set('rates.1.name', 'New')
        ->set('rates.1.rate', '3.00')
        ->call('save')
        ->assertHasNoErrors();

    expect(TaxRate::whereKey($rateToRemove->id)->exists())->toBeFalse();

    $rates = $category->rates()->orderBy('cascade_sequence')->get();
    expect($rates)->toHaveCount(2)
        ->and($rates[0]->id)->toBe($rateToEdit->id)
        ->and((string) $rates[0]->rate)->toBe('2.5000')
        ->and($rates[1]->name)->toBe('New');
});

it('validates effective_to must not precede effective_from', function () {
    Livewire::actingAs($this->admin)
        ->test(Form::class)
        ->set('name', 'Scheduled')
        ->set('code', 'scheduled')
        ->call('addRate')
        ->set('rates.0.name', 'Rate')
        ->set('rates.0.rate', '10.00')
        ->set('rates.0.effective_from', '2027-01-01')
        ->set('rates.0.effective_to', '2026-01-01')
        ->call('save')
        ->assertHasErrors(['rates.0.effective_to']);
});

it('deletes a tax category via soft delete', function () {
    $category = TaxCategory::create(['name' => 'Temp', 'code' => 'temp', 'is_default' => false]);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->call('delete', $category->id);

    expect(TaxCategory::find($category->id))->toBeNull()
        ->and(TaxCategory::withTrashed()->find($category->id))->not->toBeNull();
});

it('denies create and update to a Cashier', function () {
    $category = TaxCategory::create(['name' => 'Guarded', 'code' => 'guarded', 'is_default' => false]);

    Livewire::actingAs($this->cashier)
        ->test(Form::class)
        ->assertForbidden();

    Livewire::actingAs($this->cashier)
        ->test(Form::class, ['taxCategory' => $category])
        ->assertForbidden();

    expect($this->cashier->can('delete', $category))->toBeFalse()
        ->and($this->admin->can('delete', $category))->toBeTrue();
});
