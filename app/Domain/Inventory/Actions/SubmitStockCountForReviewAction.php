<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Inventory\Exceptions\StockCountException;
use App\Domain\Inventory\Models\StockCount;
use Illuminate\Support\Facades\DB;

final class SubmitStockCountForReviewAction
{
    public function execute(StockCount $stockCount): StockCount
    {
        return DB::transaction(function () use ($stockCount) {
            $locked = StockCount::query()->whereKey($stockCount->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== StockCount::STATUS_COUNTING) {
                throw StockCountException::notCounting();
            }

            if (! $locked->isFullyCounted()) {
                throw StockCountException::incompleteLines();
            }

            foreach ($locked->lines as $line) {
                $line->update([
                    'variance' => bcsub((string) $line->counted_quantity, (string) $line->expected_quantity, 3),
                ]);
            }

            $locked->update(['status' => StockCount::STATUS_REVIEW]);

            return $locked->fresh('lines');
        });
    }
}
