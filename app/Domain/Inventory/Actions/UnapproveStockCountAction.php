<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Exceptions\StockCountException;
use App\Domain\Inventory\InventoryService;
use App\Domain\Inventory\Models\StockCount;
use App\Domain\Inventory\Models\StockMovement;
use Illuminate\Support\Facades\DB;

final class UnapproveStockCountAction
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function execute(StockCount $stockCount, User $user): StockCount
    {
        return DB::transaction(function () use ($stockCount, $user) {
            $locked = StockCount::query()->whereKey($stockCount->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== StockCount::STATUS_APPROVED) {
                throw StockCountException::notApproved();
            }

            // Reverse the movements ApproveStockCountAction actually
            // created, not `line->variance` -- since approval now computes
            // its delta against the live quantity at approval time (not the
            // stale submit-time variance), the two can differ, and reversing
            // the wrong figure would leave real drift behind.
            $movements = StockMovement::where('source_type', $stockCount->getMorphClass())
                ->where('source_id', $stockCount->id)
                ->get();

            foreach ($movements as $movement) {
                $item = Item::findOrFail($movement->item_id);

                $this->inventory->record(
                    item: $item,
                    stockLocationId: $movement->stock_location_id,
                    quantityDelta: bcmul((string) $movement->quantity_delta, '-1', 3),
                    reason: StockMovement::REASON_COUNT,
                    source: $locked,
                    userId: $user->id,
                    stockLotId: $movement->stock_lot_id,
                );
            }

            $locked->update([
                'status' => StockCount::STATUS_REVIEW,
                'unapproved_by_user_id' => $user->id,
                'unapproved_at' => now(),
            ]);

            return $locked->fresh('lines');
        });
    }
}
