<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Queries;

use App\Domain\Inventory\Models\StockMovement;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

final class InventoryReportQuery
{
    public function movements(
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $itemId = null,
        ?int $stockLocationId = null,
        ?string $reason = null,
    ): LengthAwarePaginator {
        return StockMovement::query()
            ->with(['item', 'stockLocation'])
            ->whereBetween('occurred_at', [$from, $to])
            ->when($itemId, fn ($q) => $q->where('item_id', $itemId))
            ->when($stockLocationId, fn ($q) => $q->where('stock_location_id', $stockLocationId))
            ->when($reason, fn ($q) => $q->where('reason', $reason))
            ->latest('occurred_at')
            ->paginate(50);
    }

    /** @return Collection<int, object> plain decimal columns — no MoneyCast involved */
    public function summaryByReason(CarbonInterface $from, CarbonInterface $to, ?int $stockLocationId = null): Collection
    {
        return StockMovement::query()
            ->whereBetween('occurred_at', [$from, $to])
            ->when($stockLocationId, fn ($q) => $q->where('stock_location_id', $stockLocationId))
            ->selectRaw('reason, COUNT(*) as movement_count, SUM(CASE WHEN quantity_delta > 0 THEN quantity_delta ELSE 0 END) as quantity_in, SUM(CASE WHEN quantity_delta < 0 THEN quantity_delta ELSE 0 END) as quantity_out')
            ->groupBy('reason')
            ->get();
    }

    /** @return LazyCollection<int, StockMovement> */
    public function movementsForExport(
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $itemId = null,
        ?int $stockLocationId = null,
        ?string $reason = null,
    ): LazyCollection {
        return StockMovement::query()
            ->with(['item', 'stockLocation'])
            ->whereBetween('occurred_at', [$from, $to])
            ->when($itemId, fn ($q) => $q->where('item_id', $itemId))
            ->when($stockLocationId, fn ($q) => $q->where('stock_location_id', $stockLocationId))
            ->when($reason, fn ($q) => $q->where('reason', $reason))
            ->orderBy('occurred_at')
            ->lazy();
    }

    public function countForExport(
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $itemId = null,
        ?int $stockLocationId = null,
        ?string $reason = null,
    ): int {
        return StockMovement::query()
            ->whereBetween('occurred_at', [$from, $to])
            ->when($itemId, fn ($q) => $q->where('item_id', $itemId))
            ->when($stockLocationId, fn ($q) => $q->where('stock_location_id', $stockLocationId))
            ->when($reason, fn ($q) => $q->where('reason', $reason))
            ->count();
    }
}
