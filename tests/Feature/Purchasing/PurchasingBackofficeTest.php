<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Crm\Models\Supplier;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Purchasing\Actions\CreatePurchaseOrderAction;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\SupplierInvoice;
use App\Livewire\Purchasing\PurchaseOrders\Form as PurchaseOrderForm;
use App\Livewire\Purchasing\Receivings\Form as ReceivingForm;
use App\Livewire\Purchasing\SupplierInvoices\Index as SupplierInvoiceIndex;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->admin = User::where('username', 'admin')->firstOrFail();
    $this->stockClerk = User::factory()->create();
    $this->stockClerk->assignRole('Stock Clerk');
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
    $this->supplier = Supplier::firstOrFail();
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->stockClerk->stockLocations()->sync([$this->location->id]);
    $this->item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
});

it('denies purchase order access to a user without the purchasing ability', function () {
    $this->actingAs($this->cashier)->get(route('purchase-orders.index'))->assertForbidden();
});

it('previews subtotal, tax, and total live as lines change, before saving', function () {
    Livewire::actingAs($this->stockClerk)
        ->test(PurchaseOrderForm::class)
        ->set('lines.0.item_id', $this->item->id)
        ->set('lines.0.quantity_ordered', '10')
        ->set('lines.0.unit_cost', '0.80')
        ->assertSee('8.00')
        ->assertSee('1.20')
        ->assertSee('9.20');

    expect(PurchaseOrder::query()->exists())->toBeFalse();
});

it('creates a purchase order through the Livewire form', function () {
    $this->actingAs($this->stockClerk)->get(route('purchase-orders.create'))->assertOk();

    Livewire::actingAs($this->stockClerk)
        ->test(PurchaseOrderForm::class)
        ->set('supplier_id', $this->supplier->id)
        ->set('stock_location_id', $this->location->id)
        ->set('lines', [['item_id' => $this->item->id, 'quantity_ordered' => '5', 'unit_cost' => '0.75']])
        ->call('save')
        ->assertHasNoErrors();

    $po = PurchaseOrder::where('supplier_id', $this->supplier->id)->latest()->first();
    expect($po)->not->toBeNull()
        ->and($po->status)->toBe(PurchaseOrder::STATUS_DRAFT);
});

it('submits and approves a purchase order through the Livewire form buttons', function () {
    $po = app(CreatePurchaseOrderAction::class)->execute(
        supplier: $this->supplier,
        location: $this->location,
        creator: $this->stockClerk,
        lines: [['item_id' => $this->item->id, 'quantity_ordered' => '5', 'unit_cost' => '0.75']],
    );

    Livewire::actingAs($this->stockClerk)
        ->test(PurchaseOrderForm::class, ['purchaseOrder' => $po])
        ->call('submit')
        ->assertHasNoErrors();

    expect($po->fresh()->status)->toBe(PurchaseOrder::STATUS_SUBMITTED);

    // Stock Clerk has purchasing.manage but not purchasing.approve.
    Livewire::actingAs($this->stockClerk)
        ->test(PurchaseOrderForm::class, ['purchaseOrder' => $po->fresh()])
        ->call('approve')
        ->assertForbidden();

    Livewire::actingAs($this->admin)
        ->test(PurchaseOrderForm::class, ['purchaseOrder' => $po->fresh()])
        ->call('approve')
        ->assertHasNoErrors();

    expect($po->fresh()->status)->toBe(PurchaseOrder::STATUS_APPROVED);
});

it('pre-fills a receiving form from a purchase order query parameter', function () {
    $po = app(CreatePurchaseOrderAction::class)->execute(
        supplier: $this->supplier,
        location: $this->location,
        creator: $this->stockClerk,
        lines: [['item_id' => $this->item->id, 'quantity_ordered' => '5', 'unit_cost' => '0.75']],
    );

    $this->actingAs($this->stockClerk)
        ->get(route('receivings.create', ['purchase_order' => $po->id]))
        ->assertOk()
        ->assertSee($po->number);
});

it('records an ad-hoc receiving through the Livewire form', function () {
    $before = $this->item->quantityAt($this->location);

    Livewire::actingAs($this->stockClerk)
        ->test(ReceivingForm::class)
        ->set('supplier_id', $this->supplier->id)
        ->set('stock_location_id', $this->location->id)
        ->set('lines', [['item_id' => $this->item->id, 'quantity' => '10', 'unit_cost' => '0.75', 'purchase_order_line_id' => null, 'lot_number' => 'LOT-ADHOC-BO', 'expires_on' => null, 'selling_price' => '1.20']])
        ->call('save')
        ->assertHasNoErrors();

    expect(bcsub($this->item->fresh()->quantityAt($this->location), $before, 3))->toBe('10.000');
});

it('fully receiving a purchase order through the Livewire form advances it to received', function () {
    // Regression: purchase_order_line_id was set on each line by mount()
    // but missing from rules(), so Livewire's validate() silently dropped
    // it -- the receiving still fired (and the PO was still touched, since
    // it was passed to the action), but quantity_received never advanced,
    // leaving the PO stuck on partially_received forever even once every
    // unit had physically arrived.
    $po = app(CreatePurchaseOrderAction::class)->execute(
        supplier: $this->supplier,
        location: $this->location,
        creator: $this->admin,
        lines: [['item_id' => $this->item->id, 'quantity_ordered' => '10', 'unit_cost' => '0.75']],
    );
    $poLine = $po->lines->first();

    Livewire::actingAs($this->stockClerk)
        ->test(ReceivingForm::class, ['purchase_order' => $po->id])
        ->set('lines.0.lot_number', 'LOT-PO-FULL')
        ->set('lines.0.selling_price', '1.20')
        ->call('save')
        ->assertHasNoErrors();

    expect((string) $poLine->fresh()->quantity_received)->toBe('10.000')
        ->and($po->fresh()->status)->toBe(PurchaseOrder::STATUS_RECEIVED);
});

it('records a supplier invoice payment through the Livewire index', function () {
    $invoice = SupplierInvoice::create([
        'supplier_id' => $this->supplier->id,
        'invoice_number' => 'INV-2001',
        'invoice_date' => now()->toDateString(),
        'total' => '50.00',
        'status' => SupplierInvoice::STATUS_OPEN,
    ]);

    Livewire::actingAs($this->admin)
        ->test(SupplierInvoiceIndex::class)
        ->set("paymentAmount.{$invoice->id}", '50.00')
        ->call('recordPayment', $invoice->id)
        ->assertHasNoErrors();

    expect($invoice->fresh()->status)->toBe(SupplierInvoice::STATUS_PAID);
});

it('refuses to record a payment against a disputed invoice through the Livewire index', function () {
    $invoice = SupplierInvoice::create([
        'supplier_id' => $this->supplier->id,
        'invoice_number' => 'INV-2002',
        'invoice_date' => now()->toDateString(),
        'total' => '50.00',
        'status' => SupplierInvoice::STATUS_DISPUTED,
    ]);

    Livewire::actingAs($this->admin)
        ->test(SupplierInvoiceIndex::class)
        ->set("paymentAmount.{$invoice->id}", '50.00')
        ->call('recordPayment', $invoice->id)
        ->assertHasErrors("payment.{$invoice->id}");

    expect((string) $invoice->fresh()->paid_total->getAmount())->toBe('0.00');
});

it('forbids a Stock Clerk (no purchasing.approve) from resolving a disputed invoice', function () {
    $invoice = SupplierInvoice::create([
        'supplier_id' => $this->supplier->id,
        'invoice_number' => 'INV-2003',
        'invoice_date' => now()->toDateString(),
        'total' => '50.00',
        'status' => SupplierInvoice::STATUS_DISPUTED,
    ]);

    expect(Gate::forUser($this->stockClerk)->allows('approve', $invoice))->toBeFalse();

    Livewire::actingAs($this->stockClerk)
        ->test(SupplierInvoiceIndex::class)
        ->call('resolveDispute', $invoice->id)
        ->assertForbidden();
});

it('lets an approver resolve a disputed invoice, unblocking payment', function () {
    $invoice = SupplierInvoice::create([
        'supplier_id' => $this->supplier->id,
        'invoice_number' => 'INV-2004',
        'invoice_date' => now()->toDateString(),
        'total' => '50.00',
        'status' => SupplierInvoice::STATUS_DISPUTED,
    ]);

    Livewire::actingAs($this->admin)
        ->test(SupplierInvoiceIndex::class)
        ->call('resolveDispute', $invoice->id)
        ->assertHasNoErrors();

    expect($invoice->fresh()->status)->toBe(SupplierInvoice::STATUS_OPEN);

    Livewire::actingAs($this->admin)
        ->test(SupplierInvoiceIndex::class)
        ->set("paymentAmount.{$invoice->id}", '50.00')
        ->call('recordPayment', $invoice->id)
        ->assertHasNoErrors();

    expect($invoice->fresh()->status)->toBe(SupplierInvoice::STATUS_PAID);
});
