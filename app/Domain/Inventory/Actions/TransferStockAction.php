<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Exceptions\InventoryException;
use App\Domain\Inventory\InventoryService;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Models\StockMovement;
use Illuminate\Support\Facades\DB;

final class TransferStockAction
{
    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * @return array{out: StockMovement, in: StockMovement}
     */
    public function execute(Item $item, StockLocation $from, StockLocation $to, string $quantity, User $user, ?string $note = null): array
    {
        if (! $item->movesStock()) {
            throw InventoryException::itemDoesNotTrackStock();
        }

        if ($from->is($to)) {
            throw InventoryException::sameLocation();
        }

        if (bccomp($quantity, '0', 3) <= 0) {
            throw InventoryException::zeroQuantity();
        }

        $note = $note !== null ? trim($note) : null;
        $note = $note === '' ? null : $note;

        return DB::transaction(function () use ($item, $from, $to, $quantity, $user, $note) {
            // Locked in a canonical (location id) order, not source-then-
            // destination -- otherwise a transfer A to B run concurrently with
            // one B to A for the same item locks the two rows in opposite
            // order and deadlocks. Both locks are acquired up front, before
            // either write, so the record() calls below (still in their
            // natural out-then-in order) are only ever updating rows this
            // transaction already holds.
            [$firstId, $secondId] = $from->id < $to->id ? [$from->id, $to->id] : [$to->id, $from->id];
            $quantityAtFirst = $this->inventory->lockCurrentQuantity($item->id, $firstId);
            $quantityAtSecond = $this->inventory->lockCurrentQuantity($item->id, $secondId);
            $availableAtSource = $from->id === $firstId ? $quantityAtFirst : $quantityAtSecond;

            if (bccomp($availableAtSource, $quantity, 3) < 0) {
                throw InventoryException::insufficientStockToTransfer($availableAtSource);
            }

            $out = $this->inventory->record(
                item: $item,
                stockLocationId: $from->id,
                quantityDelta: bcmul($quantity, '-1', 3),
                reason: StockMovement::REASON_TRANSFER,
                userId: $user->id,
                note: $note,
            );

            $in = $this->inventory->record(
                item: $item,
                stockLocationId: $to->id,
                quantityDelta: $quantity,
                reason: StockMovement::REASON_TRANSFER,
                source: $out,
                userId: $user->id,
                note: $note,
            );

            return ['out' => $out, 'in' => $in];
        });
    }
}
