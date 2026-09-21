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

final class AdjustStockAction
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function execute(Item $item, StockLocation $location, string $delta, string $note, User $user): StockMovement
    {
        if (! $item->movesStock()) {
            throw InventoryException::itemDoesNotTrackStock();
        }

        if (bccomp($delta, '0', 3) === 0) {
            throw InventoryException::zeroAdjustment();
        }

        $note = trim($note);

        if ($note === '') {
            throw InventoryException::noteRequired();
        }

        return DB::transaction(function () use ($item, $location, $delta, $note, $user) {
            return $this->inventory->record(
                item: $item,
                stockLocationId: $location->id,
                quantityDelta: $delta,
                reason: StockMovement::REASON_ADJUSTMENT,
                userId: $user->id,
                note: $note,
            );
        });
    }
}
