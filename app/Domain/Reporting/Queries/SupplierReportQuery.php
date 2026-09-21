<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Queries;

use App\Domain\Purchasing\Models\Receiving;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class SupplierReportQuery
{
    /**
     * @return Collection<int, object> one row per supplier, sorted by spend desc.
     *                                 `total` is aliased to its real `Receiving` column name so `MoneyCast`
     *                                 still applies on hydration.
     */
    public function bySupplier(CarbonInterface $from, CarbonInterface $to, ?int $stockLocationId = null): Collection
    {
        return $this->baseQuery($from, $to, $stockLocationId)
            ->selectRaw('suppliers.id as supplier_id, suppliers.company_name, COUNT(*) as receiving_count, SUM(receivings.total) as total, SUM(receivings.total) / COUNT(*) as avg_receiving')
            ->groupBy('suppliers.id', 'suppliers.company_name')
            ->orderByDesc('total')
            ->get();
    }

    /** @return Collection<int, object> same shape as bySupplier(), for CSV/PDF export */
    public function forExport(CarbonInterface $from, CarbonInterface $to, ?int $stockLocationId = null): Collection
    {
        return $this->bySupplier($from, $to, $stockLocationId);
    }

    private function baseQuery(CarbonInterface $from, CarbonInterface $to, ?int $stockLocationId)
    {
        return Receiving::query()
            ->join('suppliers', 'suppliers.id', '=', 'receivings.supplier_id')
            ->where('receivings.type', Receiving::TYPE_RECEIPT)
            ->whereBetween('receivings.received_at', [$from, $to])
            ->when($stockLocationId, fn ($q) => $q->where('receivings.stock_location_id', $stockLocationId));
    }
}
