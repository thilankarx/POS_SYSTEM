<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Crm\Models\Supplier;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Purchasing\Actions\CreatePurchaseOrderAction;
use App\Domain\Purchasing\Actions\ReceiveGoodsAction;
use App\Domain\Purchasing\Models\Receiving;
use App\Livewire\Purchasing\Receivings\Show;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->admin = User::where('username', 'admin')->firstOrFail();
    $this->supplier = Supplier::firstOrFail();
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
});

it('shows an auditable receiving with purchase order and lot pricing details', function () {
    $purchaseOrder = app(CreatePurchaseOrderAction::class)->execute(
        supplier: $this->supplier,
        location: $this->location,
        creator: $this->admin,
        lines: [['item_id' => $this->item->id, 'quantity_ordered' => '10', 'unit_cost' => '0.75']],
    );

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
            'purchase_order_line_id' => $purchaseOrder->lines->firstOrFail()->id,
            'lot_number' => 'LOT-DETAIL-PAGE',
            'expires_on' => now()->addYear()->toDateString(),
            'selling_price' => '1.25',
        ]],
        supplierReference: 'DN-2048',
        comment: 'First delivery against this order.',
    );

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['receiving' => $receiving])
        ->assertSee($receiving->number)
        ->assertSee('Supplier receipt')
        ->assertSee('Stock in')
        ->assertSee($purchaseOrder->number)
        ->assertSee('DN-2048')
        ->assertSee('First delivery against this order.')
        ->assertSee('LOT-DETAIL-PAGE')
        ->assertSee('Selling price')
        ->assertSee('1.25')
        ->assertSee('Ordered 10');
});
