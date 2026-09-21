<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Purchasing\Exceptions\PurchasingException;
use App\Domain\Purchasing\Models\PurchaseOrder;

final class ApprovePurchaseOrderAction
{
    public function execute(PurchaseOrder $purchaseOrder, User $approver): PurchaseOrder
    {
        if ($purchaseOrder->status !== PurchaseOrder::STATUS_SUBMITTED) {
            throw PurchasingException::notSubmitted();
        }

        $purchaseOrder->update([
            'status' => PurchaseOrder::STATUS_APPROVED,
            'approved_by_user_id' => $approver->id,
            'approved_at' => now(),
        ]);

        return $purchaseOrder;
    }
}
