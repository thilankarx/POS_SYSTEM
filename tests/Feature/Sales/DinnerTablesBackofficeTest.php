<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Sales\Models\DinnerTable;
use App\Livewire\Sales\DinnerTables\Form as DinnerTableForm;
use App\Livewire\Sales\DinnerTables\Index as DinnerTablesIndex;
use App\Settings\BusinessProfileSettings;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->admin = User::where('username', 'admin')->firstOrFail();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();

    // Dinner-table management only exists for a restaurant install.
    $settings = app(BusinessProfileSettings::class);
    $settings->business_type = BusinessProfileSettings::BUSINESS_TYPE_RESTAURANT;
    $settings->save();
});

it('hides dinner-table management from a non-restaurant install', function () {
    $settings = app(BusinessProfileSettings::class);
    $settings->business_type = BusinessProfileSettings::BUSINESS_TYPE_HARDWARE;
    $settings->save();

    $this->actingAs($this->admin)->get(route('dinner-tables.index'))->assertNotFound();
    $this->actingAs($this->admin)->get(route('dinner-tables.create'))->assertNotFound();
});

it('forbids a Cashier from managing dinner tables', function () {
    $this->actingAs($this->cashier)->get(route('dinner-tables.index'))->assertForbidden();
    $this->actingAs($this->cashier)->get(route('dinner-tables.create'))->assertForbidden();
});

it('lets an admin create a dinner table, defaulting it to available', function () {
    Livewire::actingAs($this->admin)
        ->test(DinnerTableForm::class)
        ->set('name', 'Patio 3')
        ->set('stock_location_id', $this->location->id)
        ->set('seats', 6)
        ->call('save')
        ->assertHasNoErrors();

    $table = DinnerTable::where('name', 'Patio 3')->firstOrFail();
    expect($table->seats)->toBe(6)
        ->and($table->status)->toBe(DinnerTable::STATUS_AVAILABLE);
});

it('lets an admin edit a dinner table without touching its status', function () {
    $table = DinnerTable::create([
        'name' => 'Bar 1',
        'stock_location_id' => $this->location->id,
        'seats' => 2,
        'status' => DinnerTable::STATUS_OCCUPIED,
    ]);

    Livewire::actingAs($this->admin)
        ->test(DinnerTableForm::class, ['dinnerTable' => $table])
        ->set('seats', 3)
        ->call('save')
        ->assertHasNoErrors();

    expect($table->fresh()->seats)->toBe(3)
        ->and($table->fresh()->status)->toBe(DinnerTable::STATUS_OCCUPIED);
});

it('lets an admin delete a dinner table', function () {
    $table = DinnerTable::create([
        'name' => 'To Delete',
        'stock_location_id' => $this->location->id,
        'seats' => 4,
        'status' => DinnerTable::STATUS_AVAILABLE,
    ]);

    Livewire::actingAs($this->admin)
        ->test(DinnerTablesIndex::class)
        ->call('delete', $table->id);

    expect(DinnerTable::find($table->id))->toBeNull()
        ->and(DinnerTable::withTrashed()->find($table->id))->not->toBeNull();
});
