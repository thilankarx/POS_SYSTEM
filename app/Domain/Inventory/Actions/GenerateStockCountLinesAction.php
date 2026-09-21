<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Actions;

use App\Domain\Catalog\Models\Item;
use App\Domain\Inventory\Exceptions\StockCountException;
use App\Domain\Inventory\Models\StockCount;
use Illuminate\Support\Facades\DB;

final class GenerateStockCountLinesAction
{
    /**
     * @param  array<int, int>  $itemIds  when empty, every stocked item with nonzero on-hand quantity at the location is included
     */
    public function execute(StockCount $stockCount, array $itemIds = []): StockCount
    {
        if ($stockCount->status !== StockCount::STATUS_DRAFT) {
            throw StockCountException::notDraft();
        }

        $levels = DB::table('stock_levels')
            ->join('items', 'items.id', '=', 'stock_levels.item_id')
            ->where('stock_levels.stock_location_id', $stockCount->stock_location_id)
            ->where('items.stock_type', Item::STOCK_TYPE_STOCKED)
            ->when($itemIds !== [], fn ($q) => $q->whereIn('stock_levels.item_id', $itemIds))
            ->when($itemIds === [], fn ($q) => $q->where('stock_levels.quantity', '!=', 0))
            ->select('stock_levels.item_id', 'stock_levels.quantity')
            ->get();

        if ($levels->isEmpty()) {
            throw StockCountException::noLinesGenerated();
        }

        return DB::transaction(function () use ($stockCount, $levels) {
            foreach ($levels as $level) {
                $stockCount->lines()->create([
                    'item_id' => $level->item_id,
                    'expected_quantity' => $level->quantity,
                ]);
            }

            $stockCount->update(['status' => StockCount::STATUS_COUNTING]);

            return $stockCount->fresh('lines');
        });
    }
}
