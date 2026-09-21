<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Actions\TransferStockAction;
use App\Domain\Inventory\Exceptions\InventoryException;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Models\StockMovement;
use App\Livewire\Inventory\StockTransfers\Form;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->admin = User::where('username', 'admin')->firstOrFail();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
    $this->stockClerk = User::factory()->create();
    $this->stockClerk->assignRole('Stock Clerk');
    $this->main = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->warehouse = StockLocation::where('code', 'WH')->firstOrFail();
    $this->stockClerk->stockLocations()->sync([$this->main->id, $this->warehouse->id]);
    $this->item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
});

it('transfers stock between two locations, linking the two movements', function () {
    $mainBefore = $this->item->quantityAt($this->main);
    $warehouseBefore = $this->item->quantityAt($this->warehouse);

    $result = app(TransferStockAction::class)->execute(
        item: $this->item,
        from: $this->main,
        to: $this->warehouse,
        quantity: '15',
        user: $this->admin,
        note: 'Rebalancing stock',
    );

    expect($result['out']->reason)->toBe(StockMovement::REASON_TRANSFER)
        ->and((string) $result['out']->quantity_delta)->toBe('-15.000')
        ->and($result['in']->reason)->toBe(StockMovement::REASON_TRANSFER)
        ->and((string) $result['in']->quantity_delta)->toBe('15.000')
        ->and($result['in']->source_type)->toBe($result['out']->getMorphClass())
        ->and($result['in']->source_id)->toBe($result['out']->id);

    expect($this->item->fresh()->quantityAt($this->main))->toBe(bcsub($mainBefore, '15', 3))
        ->and($this->item->fresh()->quantityAt($this->warehouse))->toBe(bcadd($warehouseBefore, '15', 3));
});

it('refuses a same-location transfer', function () {
    app(TransferStockAction::class)->execute($this->item, $this->main, $this->main, '5', $this->admin);
})->throws(InventoryException::class, 'must be different');

it('refuses a zero or negative quantity', function () {
    app(TransferStockAction::class)->execute($this->item, $this->main, $this->warehouse, '0', $this->admin);
})->throws(InventoryException::class, 'Enter a quantity');

it('refuses to transfer a service item', function () {
    $service = Item::where('sku', 'SRV-DELIVERY')->firstOrFail();

    app(TransferStockAction::class)->execute($service, $this->main, $this->warehouse, '5', $this->admin);
})->throws(InventoryException::class, 'does not track stock');

it('denies the transfer form to a Cashier but allows a Stock Clerk', function () {
    $this->actingAs($this->cashier)->get(route('stock-transfers.create'))->assertForbidden();
    $this->actingAs($this->stockClerk)->get(route('stock-transfers.create'))->assertOk();
});

it('transfers stock through the Livewire form', function () {
    $warehouseBefore = $this->item->quantityAt($this->warehouse);

    Livewire::actingAs($this->stockClerk)
        ->test(Form::class)
        ->set('item_id', $this->item->id)
        ->set('from_location_id', $this->main->id)
        ->set('to_location_id', $this->warehouse->id)
        ->set('quantity', '7')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->item->fresh()->quantityAt($this->warehouse))->toBe(bcadd($warehouseBefore, '7', 3));
});
