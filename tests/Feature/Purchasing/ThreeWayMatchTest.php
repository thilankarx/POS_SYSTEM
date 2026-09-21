<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Crm\Models\Supplier;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Purchasing\Actions\CreatePurchaseOrderAction;
use App\Domain\Purchasing\Actions\MatchSupplierInvoiceAction;
use App\Domain\Purchasing\Actions\ReceiveGoodsAction;
use App\Domain\Purchasing\Actions\RecordSupplierInvoicePaymentAction;
use App\Domain\Purchasing\Actions\ResolveSupplierInvoiceDisputeAction;
use App\Domain\Purchasing\Exceptions\PurchasingException;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\Receiving;
use App\Domain\Purchasing\Models\SupplierInvoice;

beforeEach(function () {
    $this->seed();
    $this->admin = User::where('username', 'admin')->firstOrFail();
    $this->supplier = Supplier::firstOrFail();
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->item = Item::where('sku', 'BEV-COLA-330')->firstOrFail(); // standard tax category, 15%
});

function matchPo(): PurchaseOrder
{
    return app(CreatePurchaseOrderAction::class)->execute(
        supplier: test()->supplier,
        location: test()->location,
        creator: test()->admin,
        lines: [['item_id' => test()->item->id, 'quantity_ordered' => '10', 'unit_cost' => '0.80']],
    );
}

function receiveFully(PurchaseOrder $po): Receiving
{
    $line = $po->lines->first();

    return app(ReceiveGoodsAction::class)->execute(
        purchaseOrder: $po,
        supplier: test()->supplier,
        location: test()->location,
        user: test()->admin,
        type: Receiving::TYPE_RECEIPT,
        lines: [['item_id' => test()->item->id, 'quantity' => '10', 'unit_cost' => '0.80', 'purchase_order_line_id' => $line->id, 'lot_number' => 'LOT-3WAY']],
    );
}

function matchInvoice(array $overrides = []): SupplierInvoice
{
    return SupplierInvoice::create(array_merge([
        'supplier_id' => test()->supplier->id,
        'invoice_number' => 'INV-'.uniqid(),
        'invoice_date' => now()->toDateString(),
        'total' => '9.20',
        'status' => SupplierInvoice::STATUS_OPEN,
    ], $overrides));
}

it('stays open when the invoice total matches what was received', function () {
    $po = matchPo();
    receiveFully($po); // total 9.20 (8.00 + 1.20 tax)

    $invoice = matchInvoice(['purchase_order_id' => $po->id, 'total' => '9.20']);
    app(MatchSupplierInvoiceAction::class)->execute($invoice);

    expect($invoice->fresh()->status)->toBe(SupplierInvoice::STATUS_OPEN);
});

it('flags an invoice disputed when its total does not match what was received', function () {
    $po = matchPo();
    receiveFully($po); // total 9.20

    $invoice = matchInvoice(['purchase_order_id' => $po->id, 'total' => '5.00']);
    app(MatchSupplierInvoiceAction::class)->execute($invoice);

    expect($invoice->fresh()->status)->toBe(SupplierInvoice::STATUS_DISPUTED);
});

it('matches within the configured rounding tolerance', function () {
    $po = matchPo();
    receiveFully($po); // total 9.20

    $invoice = matchInvoice(['purchase_order_id' => $po->id, 'total' => '9.21']); // 0.01 off, tolerance is 0.01
    app(MatchSupplierInvoiceAction::class)->execute($invoice);

    expect($invoice->fresh()->status)->toBe(SupplierInvoice::STATUS_OPEN);
});

it('never disputes an invoice with no linked purchase order', function () {
    $invoice = matchInvoice(['purchase_order_id' => null, 'total' => '99999.00']);
    app(MatchSupplierInvoiceAction::class)->execute($invoice);

    expect($invoice->fresh()->status)->toBe(SupplierInvoice::STATUS_OPEN);
});

it('disputes an invoice against a purchase order with nothing received yet', function () {
    $po = matchPo();

    $invoice = matchInvoice(['purchase_order_id' => $po->id, 'total' => '9.20']);
    app(MatchSupplierInvoiceAction::class)->execute($invoice);

    expect($invoice->fresh()->status)->toBe(SupplierInvoice::STATUS_DISPUTED);
});

it('flips a disputed invoice back to open once its total is corrected', function () {
    $po = matchPo();
    receiveFully($po); // total 9.20

    $invoice = matchInvoice(['purchase_order_id' => $po->id, 'total' => '5.00']);
    app(MatchSupplierInvoiceAction::class)->execute($invoice);
    expect($invoice->fresh()->status)->toBe(SupplierInvoice::STATUS_DISPUTED);

    $invoice->update(['total' => '9.20']);
    app(MatchSupplierInvoiceAction::class)->execute($invoice);

    expect($invoice->fresh()->status)->toBe(SupplierInvoice::STATUS_OPEN);
});

it('refuses to record a payment against a disputed invoice', function () {
    $invoice = matchInvoice(['status' => SupplierInvoice::STATUS_DISPUTED, 'total' => '9.20']);

    expect(fn () => app(RecordSupplierInvoicePaymentAction::class)->execute($invoice, '9.20'))
        ->toThrow(PurchasingException::class, 'disputed');

    expect((string) $invoice->fresh()->paid_total->getAmount())->toBe('0.00');
});

it('allows payment once a dispute is resolved', function () {
    $invoice = matchInvoice(['status' => SupplierInvoice::STATUS_DISPUTED, 'total' => '9.20']);

    app(ResolveSupplierInvoiceDisputeAction::class)->execute($invoice);
    expect($invoice->fresh()->status)->toBe(SupplierInvoice::STATUS_OPEN);

    app(RecordSupplierInvoicePaymentAction::class)->execute($invoice->fresh(), '9.20');

    expect($invoice->fresh()->status)->toBe(SupplierInvoice::STATUS_PAID);
});

it('refuses to resolve a dispute on an invoice that is not disputed', function () {
    $invoice = matchInvoice(['status' => SupplierInvoice::STATUS_OPEN]);

    expect(fn () => app(ResolveSupplierInvoiceDisputeAction::class)->execute($invoice))
        ->toThrow(PurchasingException::class, 'not disputed');
});
