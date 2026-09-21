<?php

declare(strict_types=1);

namespace App\Domain\Inventory;

use App\Domain\Catalog\Models\Item;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Inventory\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The only place stock is allowed to change.
 *
 * Two rules the legacy system broke:
 *  1. Every change writes a ledger row. OSPOS mutated `item_quantities` from
 *     four call sites and wrote the ledger from only some of them, so the two
 *     drifted and `reset_quantity()` existed to paper over it.
 *  2. The projection is updated with an atomic `quantity = quantity + ?`.
 *     OSPOS read the quantity, subtracted in PHP, then wrote it back -- a lost
 *     update whenever two registers sold the same item at once.
 */
final class InventoryService
{
    /**
     * Record a stock change. Positive delta adds, negative removes.
     */
    public function record(
        Item $item,
        int $stockLocationId,
        string $quantityDelta,
        string $reason,
        ?Model $source = null,
        ?int $userId = null,
        ?int $stockLotId = null,
        ?string $unitCost = null,
        ?string $note = null,
    ): ?StockMovement {
        // Validate before any arithmetic: bcmath throws its own ValueError on
        // malformed input, and the delta is later interpolated into raw SQL.
        $quantityDelta = $this->assertNumeric($quantityDelta);

        // Services and amount-entry lines never move inventory.
        if (! $item->movesStock()) {
            return null;
        }

        if (bccomp($quantityDelta, '0', 3) === 0) {
            return null;
        }

        return DB::transaction(function () use (
            $item, $stockLocationId, $quantityDelta, $reason, $source, $userId, $stockLotId, $unitCost, $note
        ) {
            $balance = $this->applyToProjection($item->id, $stockLocationId, $quantityDelta);

            return StockMovement::create([
                'item_id' => $item->id,
                'stock_location_id' => $stockLocationId,
                'stock_lot_id' => $stockLotId,
                'quantity_delta' => $quantityDelta,
                'balance_after' => $balance,
                'unit_cost' => $unitCost,
                'reason' => $reason,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'user_id' => $userId,
                'note' => $note,
                'occurred_at' => now(),
            ]);
        });
    }

    /**
     * Atomic increment of the projection. Returns the resulting balance.
     */
    private function applyToProjection(int $itemId, int $stockLocationId, string $delta): string
    {
        StockLevel::query()->firstOrCreate(
            ['item_id' => $itemId, 'stock_location_id' => $stockLocationId],
            ['quantity' => 0, 'reserved_quantity' => 0],
        );

        DB::table('stock_levels')
            ->where('item_id', $itemId)
            ->where('stock_location_id', $stockLocationId)
            ->update(['quantity' => DB::raw('quantity + '.$this->assertNumeric($delta)), 'updated_at' => now()]);

        return (string) DB::table('stock_levels')
            ->where('item_id', $itemId)
            ->where('stock_location_id', $stockLocationId)
            ->value('quantity');
    }

    /**
     * The delta reaches raw SQL, so it must be a bare decimal literal and
     * nothing else.
     */
    private function assertNumeric(string $value): string
    {
        if (! preg_match('/^-?\d+(\.\d+)?$/', trim($value))) {
            throw new \InvalidArgumentException("Refusing to move stock by non-numeric quantity [{$value}].");
        }

        return trim($value);
    }

    /**
     * Lock and return the current live quantity for one item/location,
     * creating the row (at zero) first if it doesn't exist yet. The lock is
     * held until the caller's own transaction commits, so nothing else can
     * move this item's stock before the caller's own write does.
     *
     * For a measurement-based reconciliation (a stock count) the delta to
     * apply must be computed against whatever is true *right now*, not a
     * snapshot taken whenever the count was generated -- sales and other
     * adjustments in between are real movements the count never saw.
     */
    public function lockCurrentQuantity(int $itemId, int $stockLocationId): string
    {
        StockLevel::query()->firstOrCreate(
            ['item_id' => $itemId, 'stock_location_id' => $stockLocationId],
            ['quantity' => 0, 'reserved_quantity' => 0],
        );

        return (string) DB::table('stock_levels')
            ->where('item_id', $itemId)
            ->where('stock_location_id', $stockLocationId)
            ->lockForUpdate()
            ->value('quantity');
    }

    /**
     * Replay the ledger for one item/location and compare against the
     * projection. Backs the `stock:reconcile` command.
     *
     * @return array{ledger: string, projection: string, drift: string}
     */
    public function reconcile(int $itemId, int $stockLocationId): array
    {
        $ledger = (string) (DB::table('stock_movements')
            ->where('item_id', $itemId)
            ->where('stock_location_id', $stockLocationId)
            ->sum('quantity_delta') ?: '0');

        $projection = (string) (DB::table('stock_levels')
            ->where('item_id', $itemId)
            ->where('stock_location_id', $stockLocationId)
            ->value('quantity') ?? '0');

        return [
            'ledger' => $ledger,
            'projection' => $projection,
            'drift' => bcsub($projection, $ledger, 3),
        ];
    }
}
