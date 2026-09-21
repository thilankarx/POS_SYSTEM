<?php

declare(strict_types=1);

namespace App\Domain\Documents;

use App\Domain\Sales\Models\Sale;
use App\Settings\BusinessProfileSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;

final class SaleReceiptPdf
{
    public function build(Sale $sale): DomPdf
    {
        $sale->loadMissing([
            'lines.item',
            'taxes',
            'payments.method',
            'customer.person',
            'user',
            'terminal',
            'stockLocation',
        ]);

        return Pdf::loadView('pdf.sales.receipt', [
            'sale' => $sale,
            'business' => app(BusinessProfileSettings::class),
        ])->setPaper('a4', 'portrait');
    }
}
