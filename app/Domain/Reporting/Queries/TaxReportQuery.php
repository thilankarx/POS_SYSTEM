<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Queries;

use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\SaleTax;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class TaxReportQuery
{
    /**
     * @return Collection<int, object> one row per tax name/rate, sorted by
     *                                 tax collected desc. `taxable_amount` and `tax_amount` are aliased to
     *                                 their real `SaleTax` column names so `MoneyCast` still applies on
     *                                 hydration.
     */
    public function byRate(CarbonInterface $from, CarbonInterface $to, ?int $stockLocationId = null): Collection
    {
        return SaleTax::query()
            ->join('sales', 'sales.id', '=', 'sale_taxes.sale_id')
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereIn('sales.sale_type', [Sale::TYPE_POS, Sale::TYPE_INVOICE, Sale::TYPE_RETURN])
            ->whereBetween('sales.sold_at', [$from, $to])
            ->when($stockLocationId, fn ($q) => $q->where('sales.stock_location_id', $stockLocationId))
            ->selectRaw('sale_taxes.name, sale_taxes.rate, COUNT(*) as line_count, SUM(sale_taxes.taxable_amount) as taxable_amount, SUM(sale_taxes.tax_amount) as tax_amount')
            ->groupBy('sale_taxes.name', 'sale_taxes.rate')
            ->orderByDesc('tax_amount')
            ->get();
    }

    /** @return Collection<int, object> same shape as byRate(), for CSV/PDF export */
    public function forExport(CarbonInterface $from, CarbonInterface $to, ?int $stockLocationId = null): Collection
    {
        return $this->byRate($from, $to, $stockLocationId);
    }
}
