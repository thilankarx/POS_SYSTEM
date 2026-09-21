<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Crm\Models\Supplier;
use App\Domain\Identity\Models\Person;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Purchasing\Actions\CreatePurchaseOrderAction;
use App\Domain\Purchasing\Models\SupplierInvoice;
use App\Livewire\Purchasing\SupplierInvoices\Form;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->admin = User::where('username', 'admin')->firstOrFail();
    $this->supplier = Supplier::firstOrFail();
    $this->otherSupplier = Supplier::create([
        'person_id' => Person::factory()->create()->id,
        'company_name' => 'Second Supplier',
    ]);
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    $this->purchaseOrder = app(CreatePurchaseOrderAction::class)->execute(
        supplier: $this->supplier,
        location: $this->location,
        creator: $this->admin,
        lines: [['item_id' => $this->item->id, 'quantity_ordered' => '10', 'unit_cost' => '0.80']],
    );
});

it('shows invoice entry and matching context', function () {
    Livewire::actingAs($this->admin)
        ->test(Form::class)
        ->assertSee('Invoice information')
        ->assertSee('Match summary')
        ->assertSee('Unmatched invoice')
        ->set('supplier_id', $this->supplier->id)
        ->assertSee($this->purchaseOrder->number)
        ->set('purchase_order_id', $this->purchaseOrder->id)
        ->assertSee('Order total')
        ->assertSee('Received total')
        ->set('total', '9.20')
        ->assertSee('Review variance');
});

it('clears and rejects a purchase order belonging to another supplier', function () {
    Livewire::actingAs($this->admin)
        ->test(Form::class)
        ->set('supplier_id', $this->supplier->id)
        ->set('purchase_order_id', $this->purchaseOrder->id)
        ->set('supplier_id', $this->otherSupplier->id)
        ->assertSet('purchase_order_id', null)
        ->set('purchase_order_id', $this->purchaseOrder->id)
        ->set('invoice_number', 'WRONG-SUPPLIER-PO')
        ->set('invoice_date', now()->toDateString())
        ->set('total', '9.20')
        ->call('save')
        ->assertHasErrors('purchase_order_id');
});

it('validates the payable amount and date sequence', function () {
    Livewire::actingAs($this->admin)
        ->test(Form::class)
        ->set('supplier_id', $this->supplier->id)
        ->set('invoice_number', 'INVALID-DATES')
        ->set('invoice_date', '2026-09-20')
        ->set('due_date', '2026-09-19')
        ->set('total', '0.00')
        ->call('save')
        ->assertHasErrors(['due_date', 'total']);
});

it('records a valid supplier invoice', function () {
    Livewire::actingAs($this->admin)
        ->test(Form::class)
        ->set('supplier_id', $this->supplier->id)
        ->set('invoice_number', 'SUP-INV-1001')
        ->set('invoice_date', '2026-09-20')
        ->set('due_date', '2026-10-20')
        ->set('total', '125.50')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('supplier-invoices.index'));

    $invoice = SupplierInvoice::where('invoice_number', 'SUP-INV-1001')->firstOrFail();

    expect($invoice->supplier_id)->toBe($this->supplier->id)
        ->and((string) $invoice->total->getAmount())->toBe('125.50')
        ->and($invoice->status)->toBe(SupplierInvoice::STATUS_OPEN);
});

it('prefills the supplier and purchase order from the create URL', function () {
    $this->actingAs($this->admin)
        ->get(route('supplier-invoices.create', ['purchase_order' => $this->purchaseOrder->id]))
        ->assertOk()
        ->assertSee($this->supplier->company_name)
        ->assertSee($this->purchaseOrder->number)
        ->assertSee('Match summary');
});
