<?php

declare(strict_types=1);

use App\Domain\Crm\Models\Supplier;
use App\Domain\Purchasing\Actions\RecordSupplierInvoicePaymentAction;
use App\Domain\Purchasing\Models\SupplierInvoice;

beforeEach(function () {
    $this->seed();
    $this->supplier = Supplier::firstOrFail();
});

function makeSupplierInvoice(): SupplierInvoice
{
    return SupplierInvoice::create([
        'supplier_id' => test()->supplier->id,
        'invoice_number' => 'INV-1001',
        'invoice_date' => now()->toDateString(),
        'total' => '100.00',
        'status' => SupplierInvoice::STATUS_OPEN,
    ]);
}

it('flips to partially_paid on a partial payment', function () {
    $invoice = makeSupplierInvoice();

    $updated = app(RecordSupplierInvoicePaymentAction::class)->execute($invoice, '40.00');

    expect($updated->status)->toBe(SupplierInvoice::STATUS_PARTIALLY_PAID)
        ->and((string) $updated->paid_total->getAmount())->toBe('40.00');
});

it('flips to paid once the total is covered', function () {
    $invoice = makeSupplierInvoice();

    app(RecordSupplierInvoicePaymentAction::class)->execute($invoice, '40.00');
    $updated = app(RecordSupplierInvoicePaymentAction::class)->execute($invoice->fresh(), '60.00');

    expect($updated->status)->toBe(SupplierInvoice::STATUS_PAID)
        ->and((string) $updated->paid_total->getAmount())->toBe('100.00');
});
