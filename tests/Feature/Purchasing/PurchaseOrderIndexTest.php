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
use App\Livewire\Purchasing\PurchaseOrders\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->admin = User::where('username', 'admin')->firstOrFail();
    $this->supplier = Supplier::firstOrFail();
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
});

it('shows purchase orders as a searchable fulfilment queue', function () {
    $partialOrder = app(CreatePurchaseOrderAction::class)->execute(
        supplier: $this->supplier,
        location: $this->location,
        creator: $this->admin,
        lines: [['item_id' => $this->item->id, 'quantity_ordered' => '10', 'unit_cost' => '0.75']],
        expectedOn: now()->subDay()->toDateString(),
        note: 'Priority replenishment',
    );

    app(ReceiveGoodsAction::class)->execute(
        purchaseOrder: $partialOrder,
        supplier: $this->supplier,
        location: $this->location,
        user: $this->admin,
        type: Receiving::TYPE_RECEIPT,
        lines: [[
            'item_id' => $this->item->id,
            'quantity' => '4',
            'unit_cost' => '0.75',
            'purchase_order_line_id' => $partialOrder->lines->firstOrFail()->id,
            'lot_number' => 'LOT-PO-INDEX',
            'selling_price' => '1.20',
        ]],
    );

    $draftOrder = app(CreatePurchaseOrderAction::class)->execute(
        supplier: $this->supplier,
        location: $this->location,
        creator: $this->admin,
        lines: [['item_id' => $this->item->id, 'quantity_ordered' => '3', 'unit_cost' => '0.75']],
    );

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->assertSee($partialOrder->number)
        ->assertSee($draftOrder->number)
        ->assertSee('40%')
        ->assertSee('4 / 10')
        ->assertSee('Overdue')
        ->set('search', 'Priority replenishment')
        ->assertSee($partialOrder->number)
        ->assertDontSee($draftOrder->number)
        ->set('search', '')
        ->set('status', PurchaseOrder::STATUS_DRAFT)
        ->assertSee($draftOrder->number)
        ->assertDontSee($partialOrder->number)
        ->call('clearFilters')
        ->assertSet('status', '')
        ->assertSet('sort', 'newest')
        ->assertSee($partialOrder->number)
        ->assertSee($draftOrder->number);
});
