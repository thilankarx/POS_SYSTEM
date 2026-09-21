<?php

declare(strict_types=1);

use App\Domain\Crm\Models\Supplier;
use App\Domain\Documents\SupplierInvoicePdf;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\SupplierInvoice;
use App\Settings\BusinessProfileSettings;

beforeEach(function () {
    $this->seed();
    $this->admin = User::where('username', 'admin')->firstOrFail();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
    $this->supplier = Supplier::firstOrFail();
});

function pdfInvoice(array $overrides = []): SupplierInvoice
{
    return SupplierInvoice::create(array_merge([
        'supplier_id' => test()->supplier->id,
        'invoice_number' => 'INV-'.uniqid(),
        'invoice_date' => now()->toDateString(),
        'total' => '50.00',
        'paid_total' => '0.00',
        'status' => SupplierInvoice::STATUS_OPEN,
    ], $overrides));
}

it('renders a supplier invoice pdf', function () {
    $invoice = pdfInvoice();

    $pdf = app(SupplierInvoicePdf::class)->build($invoice);

    expect($pdf->output())->toStartWith('%PDF-');
});

it('shows the linked purchase order number when present', function () {
    $po = PurchaseOrder::create([
        'number' => 'PO-TEST-001',
        'supplier_id' => $this->supplier->id,
        'stock_location_id' => StockLocation::where('code', 'MAIN')->firstOrFail()->id,
        'created_by_user_id' => $this->admin->id,
        'status' => PurchaseOrder::STATUS_DRAFT,
    ]);
    $invoice = pdfInvoice(['purchase_order_id' => $po->id]);

    $html = view('pdf.purchasing.supplier-invoice', ['invoice' => $invoice->fresh(['purchaseOrder']), 'business' => app(BusinessProfileSettings::class)])->render();

    expect($html)->toContain('PO-TEST-001');
});

it('shows invoiced vs received figures only when disputed', function () {
    $open = pdfInvoice(['status' => SupplierInvoice::STATUS_OPEN]);
    $disputed = pdfInvoice(['status' => SupplierInvoice::STATUS_DISPUTED]);

    $openHtml = view('pdf.purchasing.supplier-invoice', ['invoice' => $open, 'business' => app(BusinessProfileSettings::class)])->render();
    $disputedHtml = view('pdf.purchasing.supplier-invoice', ['invoice' => $disputed, 'business' => app(BusinessProfileSettings::class)])->render();

    expect($openHtml)->not->toContain('Discrepancy')
        ->and($disputedHtml)->toContain('Discrepancy');
});

it('computes the balance due as total minus paid', function () {
    $invoice = pdfInvoice(['total' => '50.00', 'paid_total' => '20.00']);

    $html = view('pdf.purchasing.supplier-invoice', ['invoice' => $invoice, 'business' => app(BusinessProfileSettings::class)])->render();

    expect($html)->toContain('30.00');
});

it('shows the configured business profile name, address and phone', function () {
    $settings = app(BusinessProfileSettings::class);
    $settings->store_name = 'Acme Hardware';
    $settings->address = '123 Main St';
    $settings->phone = '555-0100';
    $settings->save();

    $invoice = pdfInvoice();

    $html = view('pdf.purchasing.supplier-invoice', ['invoice' => $invoice, 'business' => app(BusinessProfileSettings::class)])->render();

    expect($html)->toContain('Acme Hardware')
        ->and($html)->toContain('123 Main St')
        ->and($html)->toContain('555-0100');
});

it('streams the pdf for an authorized user and forbids one without purchasing.view', function () {
    $invoice = pdfInvoice();

    $this->actingAs($this->admin)->get(route('supplier-invoices.pdf', $invoice))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $this->actingAs($this->cashier)->get(route('supplier-invoices.pdf', $invoice))
        ->assertForbidden();
});
