<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Actions;

use App\Domain\Purchasing\Exceptions\PurchasingException;
use App\Domain\Purchasing\Models\PurchaseOrder;

final class SubmitPurchaseOrderAction
{
    public function execute(PurchaseOrder $purchaseOrder): PurchaseOrder
    {
        if ($purchaseOrder->status !== PurchaseOrder::STATUS_DRAFT) {
            throw PurchasingException::notDraft();
        }

        $purchaseOrder->update(['status' => PurchaseOrder::STATUS_SUBMITTED]);

        return $purchaseOrder;
    }
}
