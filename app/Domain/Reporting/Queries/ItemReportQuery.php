<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Queries;

use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\SaleLine;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class ItemReportQuery
{
    /**
     * @return Collection<int, object> one row per item, sorted by revenue desc.
     *                                 `line_total` and `cost_price` are aliased to their real `SaleLine`
     *                                 column names so `MoneyCast` still applies on hydration — see
     *                                 `SalesReportQuery`'s class doc comment for why.
     */
    public function byItem(CarbonInterface $from, CarbonInterface $to, ?int $stockLocationId = null, ?int $categoryId = null, ?string $businessType = null): Collection
    {
        return $this->baseQuery($from, $to, $stockLocationId, $categoryId, $businessType)
            ->selectRaw('items.id as item_id, items.sku, items.name as item_name, COALESCE(categories.name, "Uncategorized") as category_name, SUM(sale_lines.quantity) as quantity, SUM(sale_lines.line_total) as line_total, SUM(sale_lines.cost_price * sale_lines.quantity) as cost_price')
            ->groupBy('items.id', 'items.sku', 'items.name', 'category_name')
            ->orderByDesc('line_total')
            ->get();
    }

    /** @return Collection<int, object> same shape as byItem(), for CSV/PDF export */
    public function forExport(CarbonInterface $from, CarbonInterface $to, ?int $stockLocationId = null, ?int $categoryId = null, ?string $businessType = null): Collection
    {
        return $this->byItem($from, $to, $stockLocationId, $categoryId, $businessType);
    }

    private function baseQuery(CarbonInterface $from, CarbonInterface $to, ?int $stockLocationId, ?int $categoryId, ?string $businessType = null)
    {
        return SaleLine::query()
            ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->join('items', 'items.id', '=', 'sale_lines.item_id')
            ->leftJoin('categories', 'categories.id', '=', 'items.category_id')
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereIn('sales.sale_type', [Sale::TYPE_POS, Sale::TYPE_INVOICE, Sale::TYPE_RETURN])
            ->whereBetween('sales.sold_at', [$from, $to])
            ->when($stockLocationId, fn ($q) => $q->where('sales.stock_location_id', $stockLocationId))
            ->when($categoryId, fn ($q) => $q->where('items.category_id', $categoryId))
            // An item with no explicit business_types list is sold everywhere;
            // one with a list only shows under a type it contains.
            ->when($businessType, fn ($q) => $q->where(fn ($inner) => $inner
                ->whereNull('items.business_types')
                ->orWhereJsonLength('items.business_types', 0)
                ->orWhereJsonContains('items.business_types', $businessType)));
    }
}
