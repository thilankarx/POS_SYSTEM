<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Exceptions\StockCountException;
use App\Domain\Inventory\InventoryService;
use App\Domain\Inventory\Models\StockCount;
use App\Domain\Inventory\Models\StockMovement;
use Illuminate\Support\Facades\DB;

final class ApproveStockCountAction
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function execute(StockCount $stockCount, User $user): StockCount
    {
        return DB::transaction(function () use ($stockCount, $user) {
            // Locked and re-checked, not the possibly-stale $stockCount the
            // caller passed in -- a manager double-clicking Approve (or two
            // managers on the same count) must not both pass the status
            // check and both post every variance.
            $locked = StockCount::query()->whereKey($stockCount->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== StockCount::STATUS_REVIEW) {
                throw StockCountException::notReview();
            }

            foreach ($locked->lines as $line) {
                // The line's own `variance` is a point-in-time snapshot
                // computed when the count was submitted for review (kept for
                // display, so a reviewer sees what the count looked like at
                // that moment) -- applying it here as the stock delta would
                // silently ignore every sale or adjustment that happened
                // between then and now. Locking the live quantity and
                // re-deriving the delta against it means the count always
                // reconciles to what is true *right now*, however long the
                // count sat in review.
                $currentQuantity = $this->inventory->lockCurrentQuantity((int) $line->item_id, (int) $stockCount->stock_location_id);
                $delta = bcsub((string) $line->counted_quantity, $currentQuantity, 3);

                if (bccomp($delta, '0', 3) === 0) {
                    continue;
                }

                $this->inventory->record(
                    item: $line->item,
                    stockLocationId: $stockCount->stock_location_id,
                    quantityDelta: $delta,
                    reason: StockMovement::REASON_COUNT,
                    source: $stockCount,
                    userId: $user->id,
                    stockLotId: $line->stock_lot_id,
                );
            }

            $locked->update([
                'status' => StockCount::STATUS_APPROVED,
                'approved_by_user_id' => $user->id,
                'approved_at' => now(),
            ]);

            return $locked->fresh('lines');
        });
    }
}
