<?php

declare(strict_types=1);

namespace App\Http\Controllers\Documents;

use App\Domain\Documents\SupplierInvoicePdf;
use App\Domain\Purchasing\Models\SupplierInvoice;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class SupplierInvoicePdfController extends Controller
{
    public function __invoke(SupplierInvoice $supplierInvoice, SupplierInvoicePdf $pdf): Response
    {
        Gate::authorize('view', $supplierInvoice);

        return $pdf->build($supplierInvoice)->stream("invoice-{$supplierInvoice->invoice_number}.pdf");
    }
}
