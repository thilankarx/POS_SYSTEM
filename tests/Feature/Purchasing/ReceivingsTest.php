<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Crm\Models\Supplier;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\SerialNumber;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Models\StockLot;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Purchasing\Actions\CreatePurchaseOrderAction;
use App\Domain\Purchasing\Actions\ReceiveGoodsAction;
use App\Domain\Purchasing\Exceptions\PurchasingException;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\Receiving;
use App\Livewire\Purchasing\Receivings\Form;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->admin = User::where('username', 'admin')->firstOrFail();
    $this->supplier = Supplier::firstOrFail();
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
});

it('increases stock and writes a ledger row for an ad-hoc receiving', function () {
    $before = $this->item->quantityAt($this->location);

    $receiving = app(ReceiveGoodsAction::class)->execute(
        purchaseOrder: null,
        supplier: $this->supplier,
        location: $this->location,
        user: $this->admin,
        type: Receiving::TYPE_RECEIPT,
        lines: [['item_id' => $this->item->id, 'quantity' => '24', 'unit_cost' => '0.75', 'lot_number' => 'LOT-ADHOC']],
    );

    $after = $this->item->fresh()->quantityAt($this->location);

    expect(bcsub($after, $before, 3))->toBe('24.000')
        ->and((string) $receiving->subtotal->getAmount())->toBe('18.00')
        ->and((string) $receiving->tax_total->getAmount())->toBe('2.70')
        ->and((string) $receiving->total->getAmount())->toBe('20.70');

    $movement = StockMovement::where('source_id', $receiving->id)
        ->where('source_type', $receiving->getMorphClass())
        ->first();

    expect($movement)->not->toBeNull()
        ->and((string) $movement->quantity_delta)->toBe('24.000')
        ->and($movement->reason)->toBe(StockMovement::REASON_RECEIVING);
});

it('advances a purchase order through partially_received to received', function () {
    $po = app(CreatePurchaseOrderAction::class)->execute(
        supplier: $this->supplier,
        location: $this->location,
        creator: $this->admin,
        lines: [['item_id' => $this->item->id, 'quantity_ordered' => '10', 'unit_cost' => '0.75']],
    );
    $line = $po->lines->first();

    app(ReceiveGoodsAction::class)->execute(
        purchaseOrder: $po,
        supplier: $this->supplier,
        location: $this->location,
        user: $this->admin,
        type: Receiving::TYPE_RECEIPT,
        lines: [['item_id' => $this->item->id, 'quantity' => '4', 'unit_cost' => '0.75', 'purchase_order_line_id' => $line->id, 'lot_number' => 'LOT-PARTIAL']],
    );

    expect($po->fresh()->status)->toBe(PurchaseOrder::STATUS_PARTIALLY_RECEIVED);

    app(ReceiveGoodsAction::class)->execute(
        purchaseOrder: $po->fresh(),
        supplier: $this->supplier,
        location: $this->location,
        user: $this->admin,
        type: Receiving::TYPE_RECEIPT,
        lines: [['item_id' => $this->item->id, 'quantity' => '6', 'unit_cost' => '0.75', 'purchase_order_line_id' => $line->id, 'lot_number' => 'LOT-PARTIAL-2']],
    );

    expect($po->fresh()->status)->toBe(PurchaseOrder::STATUS_RECEIVED);
});

it('creates a stock lot when a lot number is given', function () {
    app(ReceiveGoodsAction::class)->execute(
        purchaseOrder: null,
        supplier: $this->supplier,
        location: $this->location,
        user: $this->admin,
        type: Receiving::TYPE_RECEIPT,
        lines: [[
            'item_id' => $this->item->id,
            'quantity' => '12',
            'unit_cost' => '0.75',
            'lot_number' => 'LOT-100',
            'expires_on' => now()->addYear()->toDateString(),
        ]],
    );

    $lot = StockLot::where('item_id', $this->item->id)->where('lot_number', 'LOT-100')->first();

    expect($lot)->not->toBeNull();
});

it('rejects a duplicate lot number for the same item without recording a receiving', function () {
    StockLot::create([
        'item_id' => $this->item->id,
        'lot_number' => 'LOT-DUPLICATE',
        'cost_price' => '0.75',
        'selling_price' => '1.20',
    ]);
    $receivingCount = Receiving::count();

    expect(fn () => app(ReceiveGoodsAction::class)->execute(
        purchaseOrder: null,
        supplier: $this->supplier,
        location: $this->location,
        user: $this->admin,
        type: Receiving::TYPE_RECEIPT,
        lines: [[
            'item_id' => $this->item->id,
            'quantity' => '5',
            'unit_cost' => '0.75',
            'lot_number' => 'LOT-DUPLICATE',
            'selling_price' => '1.20',
        ]],
    ))->toThrow(PurchasingException::class, 'already exists');

    expect(Receiving::count())->toBe($receivingCount);
});

it('rejects an existing lot number on the receiving form', function () {
    StockLot::create([
        'item_id' => $this->item->id,
        'lot_number' => 'LOT-FORM-DUPLICATE',
        'cost_price' => '0.75',
        'selling_price' => '1.20',
    ]);

    Livewire::actingAs($this->admin)
        ->test(Form::class)
        ->set('supplier_id', $this->supplier->id)
        ->set('stock_location_id', $this->location->id)
        ->set('lines', [[
            'item_id' => $this->item->id,
            'quantity' => '5',
            'unit_cost' => '0.75',
            'lot_number' => 'LOT-FORM-DUPLICATE',
            'expires_on' => null,
            'selling_price' => '1.20',
            'serials_text' => '',
        ]])
        ->call('save')
        ->assertHasErrors('lines.0.lot_number');
});

it('rejects the same lot number twice for an item in one receiving', function () {
    $line = [
        'item_id' => $this->item->id,
        'quantity' => '2',
        'unit_cost' => '0.75',
        'lot_number' => 'LOT-REPEATED-IN-FORM',
        'expires_on' => null,
        'selling_price' => '1.20',
        'serials_text' => '',
    ];

    Livewire::actingAs($this->admin)
        ->test(Form::class)
        ->set('supplier_id', $this->supplier->id)
        ->set('stock_location_id', $this->location->id)
        ->set('lines', [$line, $line])
        ->call('save')
        ->assertHasErrors('lines.1.lot_number');
});

it('requires expiry for an expiry-tracked item at the action boundary', function () {
    $this->item->update(['has_expiry' => true]);

    expect(fn () => app(ReceiveGoodsAction::class)->execute(
        purchaseOrder: null,
        supplier: $this->supplier,
        location: $this->location,
        user: $this->admin,
        type: Receiving::TYPE_RECEIPT,
        lines: [[
            'item_id' => $this->item->id,
            'quantity' => '5',
            'unit_cost' => '0.75',
            'lot_number' => 'LOT-NO-EXPIRY',
            'selling_price' => '1.20',
        ]],
    ))->toThrow(PurchasingException::class, 'requires an expiry date');
});

it('records a selling price on the lot when one is given at receiving', function () {
    app(ReceiveGoodsAction::class)->execute(
        purchaseOrder: null,
        supplier: $this->supplier,
        location: $this->location,
        user: $this->admin,
        type: Receiving::TYPE_RECEIPT,
        lines: [[
            'item_id' => $this->item->id,
            'quantity' => '12',
            'unit_cost' => '0.75',
            'lot_number' => 'LOT-101',
            'expires_on' => now()->addYear()->toDateString(),
            'selling_price' => '1.50',
        ]],
    );

    $lot = StockLot::where('item_id', $this->item->id)->where('lot_number', 'LOT-101')->firstOrFail();

    expect((string) $lot->selling_price->getAmount())->toBe('1.50');
});

it('leaves a lot without a selling price when none is given at receiving', function () {
    app(ReceiveGoodsAction::class)->execute(
        purchaseOrder: null,
        supplier: $this->supplier,
        location: $this->location,
        user: $this->admin,
        type: Receiving::TYPE_RECEIPT,
        lines: [[
            'item_id' => $this->item->id,
            'quantity' => '12',
            'unit_cost' => '0.75',
            'lot_number' => 'LOT-102',
            'expires_on' => now()->addYear()->toDateString(),
        ]],
    );

    $lot = StockLot::where('item_id', $this->item->id)->where('lot_number', 'LOT-102')->firstOrFail();

    expect($lot->selling_price)->toBeNull();
});

it('decreases stock for a return to supplier', function () {
    $before = $this->item->quantityAt($this->location);

    app(ReceiveGoodsAction::class)->execute(
        purchaseOrder: null,
        supplier: $this->supplier,
        location: $this->location,
        user: $this->admin,
        type: Receiving::TYPE_RETURN_TO_SUPPLIER,
        lines: [['item_id' => $this->item->id, 'quantity' => '3', 'unit_cost' => '0.75', 'lot_number' => 'LOT-RETURN']],
    );

    $after = $this->item->fresh()->quantityAt($this->location);

    expect(bcsub($before, $after, 3))->toBe('3.000');
});

it('creates serial numbers for a serialized item on receipt', function () {
    $this->item->update(['is_serialized' => true]);

    app(ReceiveGoodsAction::class)->execute(
        purchaseOrder: null,
        supplier: $this->supplier,
        location: $this->location,
        user: $this->admin,
        type: Receiving::TYPE_RECEIPT,
        lines: [[
            'item_id' => $this->item->id,
            'quantity' => '2',
            'unit_cost' => '0.75',
            'lot_number' => 'LOT-SERIAL',
            'serials' => ['SN-1001', 'SN-1002'],
        ]],
    );

    $serials = SerialNumber::where('item_id', $this->item->id)->pluck('status', 'serial');

    expect($serials->all())->toBe(['SN-1001' => 'in_stock', 'SN-1002' => 'in_stock']);
});

it('requires a lot number on the receiving form for a stocked item', function () {
    Livewire::actingAs($this->admin)
        ->test(Form::class)
        ->set('supplier_id', $this->supplier->id)
        ->set('stock_location_id', $this->location->id)
        ->set('lines', [['item_id' => $this->item->id, 'quantity' => '5', 'unit_cost' => '0.75', 'lot_number' => null, 'expires_on' => null, 'serials_text' => '']])
        ->call('save')
        ->assertHasErrors('lines.0.lot_number');
});

it('rejects a serial count that does not match the received quantity', function () {
    $this->item->update(['is_serialized' => true]);

    Livewire::actingAs($this->admin)
        ->test(Form::class)
        ->set('supplier_id', $this->supplier->id)
        ->set('stock_location_id', $this->location->id)
        ->set('lines', [['item_id' => $this->item->id, 'quantity' => '3', 'unit_cost' => '0.75', 'lot_number' => null, 'expires_on' => null, 'serials_text' => "SN-A\nSN-B"]])
        ->call('save')
        ->assertHasErrors('lines.0.serials_text');
});

it('rejects a serial number that already exists in inventory', function () {
    $this->item->update(['is_serialized' => true]);
    SerialNumber::create([
        'item_id' => $this->item->id,
        'serial' => 'SN-EXISTING',
        'status' => SerialNumber::STATUS_IN_STOCK,
    ]);

    Livewire::actingAs($this->admin)
        ->test(Form::class)
        ->set('supplier_id', $this->supplier->id)
        ->set('stock_location_id', $this->location->id)
        ->set('lines', [['item_id' => $this->item->id, 'quantity' => '1', 'unit_cost' => '0.75', 'lot_number' => null, 'expires_on' => null, 'serials_text' => 'SN-EXISTING']])
        ->call('save')
        ->assertHasErrors('lines');
});
