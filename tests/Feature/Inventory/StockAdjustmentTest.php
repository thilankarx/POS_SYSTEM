<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Actions\AdjustStockAction;
use App\Domain\Inventory\Exceptions\InventoryException;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Models\StockMovement;
use App\Livewire\Inventory\StockAdjustments\Form;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->admin = User::where('username', 'admin')->firstOrFail();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
    $this->stockClerk = User::factory()->create();
    $this->stockClerk->assignRole('Stock Clerk');
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->stockClerk->stockLocations()->sync([$this->location->id]);
    $this->item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
});

it('increases stock with a required note', function () {
    $before = $this->item->quantityAt($this->location);

    $movement = app(AdjustStockAction::class)->execute(
        item: $this->item,
        location: $this->location,
        delta: '10',
        note: 'Found extra stock during cycle count',
        user: $this->admin,
    );

    expect($movement->reason)->toBe(StockMovement::REASON_ADJUSTMENT)
        ->and($movement->note)->toBe('Found extra stock during cycle count')
        ->and((string) $movement->quantity_delta)->toBe('10.000')
        ->and($this->item->fresh()->quantityAt($this->location))->toBe(bcadd($before, '10', 3));
});

it('decreases stock', function () {
    $before = $this->item->quantityAt($this->location);

    app(AdjustStockAction::class)->execute($this->item, $this->location, '-5', 'Damaged in storage', $this->admin);

    expect($this->item->fresh()->quantityAt($this->location))->toBe(bcsub($before, '5', 3));
});

it('refuses a zero adjustment', function () {
    app(AdjustStockAction::class)->execute($this->item, $this->location, '0', 'Note', $this->admin);
})->throws(InventoryException::class, 'increase or decrease');

it('refuses a blank note', function () {
    app(AdjustStockAction::class)->execute($this->item, $this->location, '5', '   ', $this->admin);
})->throws(InventoryException::class, 'note is required');

it('refuses to adjust a service item', function () {
    $service = Item::where('sku', 'SRV-DELIVERY')->firstOrFail();

    app(AdjustStockAction::class)->execute($service, $this->location, '5', 'Note', $this->admin);
})->throws(InventoryException::class, 'does not track stock');

it('denies the adjust form to a Cashier but allows a Stock Clerk', function () {
    $this->actingAs($this->cashier)->get(route('stock-adjustments.create'))->assertForbidden();
    $this->actingAs($this->stockClerk)->get(route('stock-adjustments.create'))->assertOk();
});

it('adjusts stock through the Livewire form', function () {
    $before = $this->item->quantityAt($this->location);

    Livewire::actingAs($this->stockClerk)
        ->test(Form::class)
        ->set('stock_location_id', $this->location->id)
        ->set('item_id', $this->item->id)
        ->set('direction', 'increase')
        ->set('quantity', '3')
        ->set('note', 'Manual correction')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->item->fresh()->quantityAt($this->location))->toBe(bcadd($before, '3', 3));
});
