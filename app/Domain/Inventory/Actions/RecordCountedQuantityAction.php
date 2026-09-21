<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Exceptions\StockCountException;
use App\Domain\Inventory\Models\StockCount;
use App\Domain\Inventory\Models\StockCountLine;

final class RecordCountedQuantityAction
{
    public function execute(StockCountLine $line, string $quantity, User $user): StockCountLine
    {
        if ($line->stockCount->status !== StockCount::STATUS_COUNTING) {
            throw StockCountException::notCounting();
        }

        $line->update([
            'counted_quantity' => $quantity,
            'counted_by_user_id' => $user->id,
            'counted_at' => now(),
        ]);

        return $line;
    }
}
