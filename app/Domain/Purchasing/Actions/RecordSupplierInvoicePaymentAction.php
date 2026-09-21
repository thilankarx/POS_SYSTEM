<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Actions;

use App\Domain\Purchasing\Exceptions\PurchasingException;
use App\Domain\Purchasing\Models\SupplierInvoice;
use App\Support\Money\Money as MoneySupport;
use Illuminate\Support\Facades\DB;

final class RecordSupplierInvoicePaymentAction
{
    public function execute(SupplierInvoice $invoice, string $amount): SupplierInvoice
    {
        return DB::transaction(function () use ($invoice, $amount) {
            // Locked and re-read, not the possibly-stale $invoice the caller
            // passed in -- two clerks recording a payment on the same
            // invoice at once must not both compute paid_total from the
            // same pre-payment balance.
            $locked = SupplierInvoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === SupplierInvoice::STATUS_DISPUTED) {
                throw PurchasingException::invoiceDisputed();
            }

            $newPaidTotal = MoneySupport::of($locked->paid_total)->plus(MoneySupport::of($amount));

            if ($newPaidTotal->isGreaterThan(MoneySupport::of($locked->total))) {
                throw PurchasingException::paymentExceedsInvoiceTotal();
            }

            $locked->update([
                'paid_total' => $newPaidTotal,
                'status' => $newPaidTotal->isGreaterThanOrEqualTo(MoneySupport::of($locked->total))
                    ? SupplierInvoice::STATUS_PAID
                    : SupplierInvoice::STATUS_PARTIALLY_PAID,
            ]);

            return $locked;
        });
    }
}
