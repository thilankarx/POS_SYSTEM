<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Crm\Models\Supplier;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Livewire\Purchasing\PurchaseOrders\Form;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->admin = User::where('username', 'admin')->firstOrFail();
    $this->supplier = Supplier::firstOrFail();
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
});

it('builds and saves a purchase-order draft with live line totals', function () {
    $component = Livewire::actingAs($this->admin)
        ->test(Form::class)
        ->assertSee('New purchase order')
        ->assertSee('Order information')
        ->assertSee('Order items')
        ->assertSee('Line total')
        ->assertSee('Save draft')
        ->set('supplier_id', $this->supplier->id)
        ->set('stock_location_id', $this->location->id)
        ->set('expected_on', now()->addWeek()->toDateString())
        ->set('note', 'Deliver before noon.')
        ->set('lines.0.item_id', $this->item->id)
        ->set('lines.0.quantity_ordered', '5')
        ->set('lines.0.unit_cost', '2.50')
        ->assertSee('12.50')
        ->call('save')
        ->assertHasNoErrors();

    $purchaseOrder = PurchaseOrder::latest('id')->firstOrFail();

    $component->assertRedirect(route('purchase-orders.edit', $purchaseOrder));

    expect($purchaseOrder->status)->toBe(PurchaseOrder::STATUS_DRAFT)
        ->and($purchaseOrder->note)->toBe('Deliver before noon.')
        ->and((string) $purchaseOrder->lines->firstOrFail()->quantity_ordered)->toBe('5.000');
});

it('rejects a zero-quantity purchase-order line', function () {
    Livewire::actingAs($this->admin)
        ->test(Form::class)
        ->set('supplier_id', $this->supplier->id)
        ->set('stock_location_id', $this->location->id)
        ->set('lines.0.item_id', $this->item->id)
        ->set('lines.0.quantity_ordered', '0')
        ->set('lines.0.unit_cost', '2.50')
        ->call('save')
        ->assertHasErrors('lines.0.quantity_ordered');
});
