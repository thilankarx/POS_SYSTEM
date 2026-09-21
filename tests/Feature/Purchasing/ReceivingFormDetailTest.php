<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Crm\Models\Supplier;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Purchasing\Actions\CreatePurchaseOrderAction;
use App\Domain\Purchasing\Actions\ReceiveGoodsAction;
use App\Domain\Purchasing\Models\Receiving;
use App\Livewire\Purchasing\Receivings\Form;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->admin = User::where('username', 'admin')->firstOrFail();
    $this->supplier = Supplier::firstOrFail();
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();

    $this->purchaseOrder = app(CreatePurchaseOrderAction::class)->execute(
        supplier: $this->supplier,
        location: $this->location,
        creator: $this->admin,
        lines: [['item_id' => $this->item->id, 'quantity_ordered' => '10', 'unit_cost' => '0.75']],
        expectedOn: now()->addWeek()->toDateString(),
    );

    app(ReceiveGoodsAction::class)->execute(
        purchaseOrder: $this->purchaseOrder,
        supplier: $this->supplier,
        location: $this->location,
        user: $this->admin,
        type: Receiving::TYPE_RECEIPT,
        lines: [[
            'item_id' => $this->item->id,
            'quantity' => '4',
            'unit_cost' => '0.75',
            'purchase_order_line_id' => $this->purchaseOrder->lines->firstOrFail()->id,
            'lot_number' => 'LOT-PREVIOUS-RECEIPT',
            'selling_price' => '1.20',
        ]],
    );
});

it('shows purchase order progress and remaining quantities while receiving', function () {
    $this->item->update(['is_active' => false]);

    Livewire::actingAs($this->admin)
        ->test(Form::class, ['purchase_order' => $this->purchaseOrder->id])
        ->assertSee($this->purchaseOrder->number)
        ->assertSee($this->item->name)
        ->assertSee('Ordered')
        ->assertSee('Previously received')
        ->assertSee('Remaining')
        ->assertSee('Maximum 6')
        ->assertSee('Selling price')
        ->assertSee('Lot number')
        ->assertSee('Expiry date')
        ->assertSee('(optional)');
});

it('rejects quantities above the remaining purchase order quantity', function () {
    Livewire::actingAs($this->admin)
        ->test(Form::class, ['purchase_order' => $this->purchaseOrder->id])
        ->set('lines.0.quantity', '7')
        ->set('lines.0.lot_number', 'LOT-OVER-RECEIPT')
        ->set('lines.0.selling_price', '1.20')
        ->call('save')
        ->assertHasErrors('lines.0.quantity');
});

it('records a purchase order receiving as a receipt even if the client tampers with its type', function () {
    Livewire::actingAs($this->admin)
        ->test(Form::class, ['purchase_order' => $this->purchaseOrder->id])
        ->set('type', Receiving::TYPE_RETURN_TO_SUPPLIER)
        ->set('lines.0.lot_number', 'LOT-FINAL-RECEIPT')
        ->set('lines.0.selling_price', '1.20')
        ->call('save')
        ->assertHasNoErrors();

    expect(Receiving::latest('id')->firstOrFail()->type)->toBe(Receiving::TYPE_RECEIPT);

    Livewire::actingAs($this->admin)
        ->test(Form::class, ['purchase_order' => $this->purchaseOrder->id])
        ->assertSet('lines', [])
        ->assertSee('This purchase order has no outstanding quantities.');
});
