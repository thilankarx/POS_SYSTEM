<?php

declare(strict_types=1);

namespace App\Http\Controllers\Documents;

use App\Domain\Documents\SaleReceiptPdf;
use App\Domain\Sales\Models\Sale;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class SaleReceiptController extends Controller
{
    public function __invoke(Sale $sale, SaleReceiptPdf $pdf): Response
    {
        Gate::authorize('view', $sale);

        $filename = ($sale->sale_type === Sale::TYPE_INVOICE ? 'invoice-' : 'receipt-')
            .($sale->invoice_number ?? $sale->number).'.pdf';

        return $pdf->build($sale)->stream($filename);
    }
}
