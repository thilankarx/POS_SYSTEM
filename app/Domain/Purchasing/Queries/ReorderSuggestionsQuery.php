<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Queries;

use App\Domain\Catalog\Models\Item;
use App\Domain\Inventory\Models\StockLocation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class ReorderSuggestionsQuery
{
    /**
     * Active, stocked items with a supplier whose on-hand quantity at the
     * given location has fallen below their reorder level. `reorder_level`
     * of 0 means "never auto-suggest", matching the column's default.
     *
     * @return Collection<int, Item>
     */
    public function forLocation(StockLocation $location): Collection
    {
        return Item::query()
            ->active()
            ->where('items.stock_type', Item::STOCK_TYPE_STOCKED)
            ->whereNotNull('items.supplier_id')
            ->where('items.reorder_level', '>', 0)
            ->leftJoin('stock_levels', function ($join) use ($location) {
                $join->on('stock_levels.item_id', '=', 'items.id')
                    ->where('stock_levels.stock_location_id', $location->id);
            })
            ->whereRaw('COALESCE(stock_levels.quantity, 0) < items.reorder_level')
            ->with('supplier')
            ->select('items.*', DB::raw('COALESCE(stock_levels.quantity, 0) as on_hand'))
            ->orderBy('items.name')
            ->get();
    }

    public function suggestedQuantity(Item $item): string
    {
        if ($item->reorder_quantity !== null && bccomp((string) $item->reorder_quantity, '0', 3) > 0) {
            return (string) $item->reorder_quantity;
        }

        return bcsub((string) $item->reorder_level, (string) $item->on_hand, 3);
    }
}
