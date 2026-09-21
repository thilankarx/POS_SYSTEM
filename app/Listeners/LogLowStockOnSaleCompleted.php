<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Purchasing\Queries\ReorderSuggestionsQuery;
use App\Domain\Sales\Events\SaleCompleted;

final class LogLowStockOnSaleCompleted
{
    public function __construct(private readonly ReorderSuggestionsQuery $query) {}

    public function handle(SaleCompleted $event): void
    {
        $sale = $event->sale;
        $stockLocation = $sale->stockLocation;

        if ($stockLocation === null) {
            return;
        }

        $itemIds = $sale->lines->pluck('item_id')->unique();

        if ($itemIds->isEmpty()) {
            return;
        }

        $lowStockItems = $this->query->forLocation($stockLocation)
            ->whereIn('id', $itemIds);

        foreach ($lowStockItems as $item) {
            activity('inventory')
                ->performedOn($item)
                ->event('low_stock')
                ->withProperties([
                    'stock_location_id' => $stockLocation->id,
                    'on_hand' => (string) $item->on_hand,
                    'reorder_level' => (string) $item->reorder_level,
                    'suggested_quantity' => $this->query->suggestedQuantity($item),
                    'triggered_by_sale_id' => $sale->id,
                ])
                ->log("Low stock: {$item->name} at {$stockLocation->name} ({$item->on_hand}/{$item->reorder_level})");
        }
    }
}
