<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Actions;

use App\Domain\Purchasing\Models\SupplierInvoice;

/**
 * Header-level three-way match: compares an invoice's total against what
 * was actually received against its linked PO. No line items exist on a
 * supplier invoice in this schema, so this is a total-vs-total comparison,
 * not a per-line reconciliation.
 */
final class MatchSupplierInvoiceAction
{
    public function execute(SupplierInvoice $invoice): SupplierInvoice
    {
        if ($invoice->purchase_order_id === null) {
            return $invoice;
        }

        if (! in_array($invoice->status, [SupplierInvoice::STATUS_OPEN, SupplierInvoice::STATUS_DISPUTED], true)) {
            return $invoice;
        }

        $diff = bcsub((string) $invoice->total->getAmount(), (string) $invoice->receivedTotal()->getAmount(), 4);
        $absDiff = bccomp($diff, '0', 4) < 0 ? bcmul($diff, '-1', 4) : $diff;
        $tolerance = (string) config('pos.purchasing.match_tolerance', '0.01');

        $invoice->update([
            'status' => bccomp($absDiff, $tolerance, 4) > 0
                ? SupplierInvoice::STATUS_DISPUTED
                : SupplierInvoice::STATUS_OPEN,
        ]);

        return $invoice;
    }
}
