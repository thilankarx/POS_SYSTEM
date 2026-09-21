<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Actions;

use App\Domain\Purchasing\Exceptions\PurchasingException;
use App\Domain\Purchasing\Models\SupplierInvoice;

final class ResolveSupplierInvoiceDisputeAction
{
    public function execute(SupplierInvoice $invoice): SupplierInvoice
    {
        if ($invoice->status !== SupplierInvoice::STATUS_DISPUTED) {
            throw PurchasingException::notDisputed();
        }

        $invoice->update(['status' => SupplierInvoice::STATUS_OPEN]);

        return $invoice;
    }
}
