<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Crm\Models\Supplier;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Purchasing\Actions\CreatePurchaseOrderAction;
use App\Domain\Purchasing\Actions\ReceiveGoodsAction;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\Receiving;
use App\Livewire\Purchasing\PurchaseOrders\Form;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->admin = User::where('username', 'admin')->firstOrFail();
    $this->supplier = Supplier::firstOrFail();
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
});

it('shows full order and receiving details for a partially received purchase order', function () {
    $purchaseOrder = app(CreatePurchaseOrderAction::class)->execute(
        supplier: $this->supplier,
        location: $this->location,
        creator: $this->admin,
        lines: [['item_id' => $this->item->id, 'quantity_ordered' => '10', 'unit_cost' => '0.75']],
        expectedOn: now()->addWeek()->toDateString(),
        note: 'Deliver to the rear stock entrance.',
    );
    $purchaseOrderLine = $purchaseOrder->lines->firstOrFail();

    $receiving = app(ReceiveGoodsAction::class)->execute(
        purchaseOrder: $purchaseOrder,
        supplier: $this->supplier,
        location: $this->location,
        user: $this->admin,
        type: Receiving::TYPE_RECEIPT,
        lines: [[
            'item_id' => $this->item->id,
            'quantity' => '4',
            'unit_cost' => '0.75',
            'purchase_order_line_id' => $purchaseOrderLine->id,
            'lot_number' => 'LOT-PO-DETAIL',
            'expires_on' => now()->addYear()->toDateString(),
        ]],
        supplierReference: 'SUP-INV-1001',
        comment: 'First delivery.',
    );

    expect($purchaseOrder->fresh()->status)->toBe(PurchaseOrder::STATUS_PARTIALLY_RECEIVED);

    Livewire::actingAs($this->admin)
        ->test(Form::class, ['purchaseOrder' => $purchaseOrder->fresh()])
        ->assertSee('Order details')
        ->assertSee('Ordered')
        ->assertSee('Received')
        ->assertSee('Remaining')
        ->assertSee('Partial')
        ->assertSee('Receiving history')
        ->assertSee($receiving->number)
        ->assertSee('SUP-INV-1001')
        ->assertSee('LOT-PO-DETAIL')
        ->assertSee('First delivery.')
        ->assertSee('Deliver to the rear stock entrance.');
});

it('keeps draft purchase orders editable', function () {
    $purchaseOrder = app(CreatePurchaseOrderAction::class)->execute(
        supplier: $this->supplier,
        location: $this->location,
        creator: $this->admin,
        lines: [['item_id' => $this->item->id, 'quantity_ordered' => '10', 'unit_cost' => '0.75']],
    );

    Livewire::actingAs($this->admin)
        ->test(Form::class, ['purchaseOrder' => $purchaseOrder])
        ->assertSee('Submit for approval')
        ->assertSee('Save')
        ->assertDontSee('Receiving history');
});
