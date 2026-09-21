<?php

declare(strict_types=1);

namespace App\Domain\Documents;

use App\Domain\Purchasing\Models\SupplierInvoice;
use App\Settings\BusinessProfileSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;

final class SupplierInvoicePdf
{
    public function build(SupplierInvoice $invoice): DomPdf
    {
        $invoice->loadMissing(['supplier.person', 'purchaseOrder']);

        return Pdf::loadView('pdf.purchasing.supplier-invoice', [
            'invoice' => $invoice,
            'business' => app(BusinessProfileSettings::class),
        ])->setPaper('a4', 'portrait');
    }
}
