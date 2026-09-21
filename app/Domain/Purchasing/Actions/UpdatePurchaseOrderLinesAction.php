<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Actions;

use App\Domain\Purchasing\Exceptions\PurchasingException;
use App\Domain\Purchasing\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

final class UpdatePurchaseOrderLinesAction
{
    public function __construct(private readonly CreatePurchaseOrderAction $creator) {}

    /**
     * @param  array<int, array{item_id: int, quantity_ordered: string, unit_cost: string}>  $lines
     */
    public function execute(PurchaseOrder $purchaseOrder, array $lines): PurchaseOrder
    {
        if ($purchaseOrder->status !== PurchaseOrder::STATUS_DRAFT) {
            throw PurchasingException::notDraft();
        }

        if ($lines === []) {
            throw PurchasingException::emptyLines();
        }

        return DB::transaction(function () use ($purchaseOrder, $lines) {
            $purchaseOrder->lines()->delete();
            $this->creator->writeLines($purchaseOrder, $lines);

            return $purchaseOrder->fresh('lines');
        });
    }
}
