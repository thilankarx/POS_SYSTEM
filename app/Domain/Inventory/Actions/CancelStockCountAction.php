<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Exceptions\StockCountException;
use App\Domain\Inventory\Models\StockCount;

final class CancelStockCountAction
{
    public function execute(StockCount $stockCount): StockCount
    {
        if (in_array($stockCount->status, [StockCount::STATUS_APPROVED, StockCount::STATUS_CANCELLED], true)) {
            throw StockCountException::alreadyClosed();
        }

        $stockCount->update(['status' => StockCount::STATUS_CANCELLED]);

        return $stockCount;
    }
}
