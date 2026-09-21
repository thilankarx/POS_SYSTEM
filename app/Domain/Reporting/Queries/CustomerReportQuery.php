<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Queries;

use App\Domain\Sales\Models\Sale;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class CustomerReportQuery
{
    /**
     * @return Collection<int, object> one row per customer (plus a "Walk-in"
     *                                 row for sales with no `customer_id`), sorted by spend desc. `total`
     *                                 is aliased to its real `Sale` column name so `MoneyCast` still
     *                                 applies on hydration.
     */
    public function byCustomer(CarbonInterface $from, CarbonInterface $to, ?int $stockLocationId = null): Collection
    {
        return Sale::query()->completed()->revenue()
            ->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
            ->leftJoin('people', 'people.id', '=', 'customers.person_id')
            ->whereBetween('sales.sold_at', [$from, $to])
            ->when($stockLocationId, fn ($q) => $q->where('sales.stock_location_id', $stockLocationId))
            ->selectRaw('sales.customer_id, COALESCE(NULLIF(customers.company_name, ""), NULLIF(TRIM(CONCAT(people.first_name, " ", people.last_name)), ""), "Walk-in") as customer_name, COUNT(*) as sale_count, SUM(sales.total) as total, MAX(sales.sold_at) as last_purchase_at')
            ->groupBy('sales.customer_id', 'customer_name')
            ->orderByDesc('total')
            ->get();
    }

    /** @return Collection<int, object> same shape as byCustomer(), for CSV/PDF export */
    public function forExport(CarbonInterface $from, CarbonInterface $to, ?int $stockLocationId = null): Collection
    {
        return $this->byCustomer($from, $to, $stockLocationId);
    }
}
