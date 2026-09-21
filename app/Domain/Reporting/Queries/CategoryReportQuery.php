<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Queries;

use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\SaleLine;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class CategoryReportQuery
{
    /**
     * @return Collection<int, object> one row per category, sorted by revenue desc.
     *                                 `line_total` and `cost_price` are aliased to their real `SaleLine`
     *                                 column names so `MoneyCast` still applies on hydration.
     */
    public function byCategory(CarbonInterface $from, CarbonInterface $to, ?int $stockLocationId = null): Collection
    {
        return $this->baseQuery($from, $to, $stockLocationId)
            ->selectRaw('COALESCE(categories.name, "Uncategorized") as category_name, SUM(sale_lines.quantity) as quantity, SUM(sale_lines.line_total) as line_total, SUM(sale_lines.cost_price * sale_lines.quantity) as cost_price')
            ->groupBy('category_name')
            ->orderByDesc('line_total')
            ->get();
    }

    /** @return Collection<int, object> same shape as byCategory(), for CSV/PDF export */
    public function forExport(CarbonInterface $from, CarbonInterface $to, ?int $stockLocationId = null): Collection
    {
        return $this->byCategory($from, $to, $stockLocationId);
    }

    private function baseQuery(CarbonInterface $from, CarbonInterface $to, ?int $stockLocationId)
    {
        return SaleLine::query()
            ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->join('items', 'items.id', '=', 'sale_lines.item_id')
            ->leftJoin('categories', 'categories.id', '=', 'items.category_id')
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereIn('sales.sale_type', [Sale::TYPE_POS, Sale::TYPE_INVOICE, Sale::TYPE_RETURN])
            ->whereBetween('sales.sold_at', [$from, $to])
            ->when($stockLocationId, fn ($q) => $q->where('sales.stock_location_id', $stockLocationId));
    }
}
