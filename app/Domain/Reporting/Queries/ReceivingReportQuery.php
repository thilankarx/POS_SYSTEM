<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Queries;

use App\Domain\Purchasing\Models\Receiving;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

final class ReceivingReportQuery
{
    public function receivings(
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $supplierId = null,
        ?int $stockLocationId = null,
        ?string $type = null,
    ): LengthAwarePaginator {
        return $this->filtered($from, $to, $supplierId, $stockLocationId, $type)
            ->with(['supplier', 'stockLocation'])
            ->latest('received_at')
            ->paginate(50);
    }

    /** @return Collection<int, object> */
    public function summaryByType(CarbonInterface $from, CarbonInterface $to, ?int $stockLocationId = null): Collection
    {
        return Receiving::query()
            ->whereBetween('received_at', [$from, $to])
            ->when($stockLocationId, fn ($q) => $q->where('stock_location_id', $stockLocationId))
            ->selectRaw('type, COUNT(*) as receiving_count, SUM(total) as total')
            ->groupBy('type')
            ->get();
    }

    /** @return LazyCollection<int, Receiving> */
    public function receivingsForExport(
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $supplierId = null,
        ?int $stockLocationId = null,
        ?string $type = null,
    ): LazyCollection {
        return $this->filtered($from, $to, $supplierId, $stockLocationId, $type)
            ->with(['supplier', 'stockLocation'])
            ->orderBy('received_at')
            ->lazy();
    }

    public function countForExport(
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $supplierId = null,
        ?int $stockLocationId = null,
        ?string $type = null,
    ): int {
        return $this->filtered($from, $to, $supplierId, $stockLocationId, $type)->count();
    }

    private function filtered(
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $supplierId,
        ?int $stockLocationId,
        ?string $type,
    ) {
        return Receiving::query()
            ->whereBetween('received_at', [$from, $to])
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->when($stockLocationId, fn ($q) => $q->where('stock_location_id', $stockLocationId))
            ->when($type, fn ($q) => $q->where('type', $type));
    }
}
