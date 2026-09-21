<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Queries;

use App\Domain\Reporting\Data\SalesSummary;
use App\Domain\Sales\Models\Payment;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\SaleLine;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

/**
 * Every aggregate here is aliased to a real MoneyCast column name (subtotal,
 * discount_total, ...) so that hydrating through the Eloquent builder (not
 * DB::table()) casts the raw SUM() result straight into a Brick\Money\Money
 * instance for free — no manual Money::of() wrapping needed.
 */
final class SalesReportQuery
{
    public function summary(CarbonInterface $from, CarbonInterface $to, ?int $stockLocationId = null): SalesSummary
    {
        $row = Sale::query()->completed()->revenue()
            ->whereBetween('sold_at', [$from, $to])
            ->when($stockLocationId, fn ($q) => $q->where('stock_location_id', $stockLocationId))
            ->selectRaw('COUNT(*) as sale_count, COALESCE(SUM(subtotal),0) as subtotal, COALESCE(SUM(discount_total),0) as discount_total, COALESCE(SUM(tax_total),0) as tax_total, COALESCE(SUM(total),0) as total, COALESCE(SUM(cost_total),0) as cost_total')
            ->first();

        return new SalesSummary(
            saleCount: (int) $row->sale_count,
            subtotal: $row->subtotal,
            discountTotal: $row->discount_total,
            taxTotal: $row->tax_total,
            total: $row->total,
            costTotal: $row->cost_total,
        );
    }

    /** @return Collection<int, Sale> one row per calendar day, partially hydrated */
    public function byDay(CarbonInterface $from, CarbonInterface $to, ?int $stockLocationId = null): Collection
    {
        return Sale::query()->completed()->revenue()
            ->whereBetween('sold_at', [$from, $to])
            ->when($stockLocationId, fn ($q) => $q->where('stock_location_id', $stockLocationId))
            ->selectRaw('DATE(sold_at) as day, COUNT(*) as sale_count, SUM(subtotal) as subtotal, SUM(discount_total) as discount_total, SUM(tax_total) as tax_total, SUM(total) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->get();
    }

    /** @return Collection<int, SaleLine> one row per category, partially hydrated */
    public function byCategory(CarbonInterface $from, CarbonInterface $to, ?int $stockLocationId = null): Collection
    {
        return SaleLine::query()
            ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->join('items', 'items.id', '=', 'sale_lines.item_id')
            ->leftJoin('categories', 'categories.id', '=', 'items.category_id')
            // Mirrors Sale::scopeCompleted()/scopeRevenue() — can't call those scopes
            // directly on a joined, column-qualified query.
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereIn('sales.sale_type', [Sale::TYPE_POS, Sale::TYPE_INVOICE, Sale::TYPE_RETURN])
            ->whereBetween('sales.sold_at', [$from, $to])
            ->when($stockLocationId, fn ($q) => $q->where('sales.stock_location_id', $stockLocationId))
            ->selectRaw('COALESCE(categories.name, "Uncategorized") as category_name, SUM(sale_lines.quantity) as quantity, SUM(sale_lines.line_subtotal) as line_subtotal, SUM(sale_lines.line_tax) as line_tax, SUM(sale_lines.line_total) as line_total')
            ->groupBy('category_name')
            ->orderByDesc('line_total')
            ->get();
    }

    /** @return Collection<int, Payment> one row per payment method, partially hydrated */
    public function byPaymentMethod(CarbonInterface $from, CarbonInterface $to, ?int $stockLocationId = null): Collection
    {
        return Payment::query()
            ->join('sales', 'sales.id', '=', 'payments.sale_id')
            ->join('payment_methods', 'payment_methods.id', '=', 'payments.payment_method_id')
            ->where('payments.status', Payment::STATUS_CAPTURED)
            ->whereBetween('sales.sold_at', [$from, $to])
            ->when($stockLocationId, fn ($q) => $q->where('sales.stock_location_id', $stockLocationId))
            ->selectRaw('payment_methods.name as method_name, SUM(payments.amount) as amount, COUNT(*) as payment_count')
            ->groupBy('method_name')
            ->orderByDesc('amount')
            ->get();
    }

    /** @return Collection<int, SaleLine> top items by quantity sold, partially hydrated */
    public function topItems(CarbonInterface $from, CarbonInterface $to, ?int $stockLocationId = null, int $limit = 10): Collection
    {
        return SaleLine::query()
            ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->where('sales.status', Sale::STATUS_COMPLETED)
            ->whereIn('sales.sale_type', [Sale::TYPE_POS, Sale::TYPE_INVOICE, Sale::TYPE_RETURN])
            ->whereBetween('sales.sold_at', [$from, $to])
            ->when($stockLocationId, fn ($q) => $q->where('sales.stock_location_id', $stockLocationId))
            ->selectRaw('sale_lines.item_name, SUM(sale_lines.quantity) as quantity, SUM(sale_lines.line_total) as line_total')
            ->groupBy('sale_lines.item_name')
            ->orderByDesc('quantity')
            ->limit($limit)
            ->get();
    }

    /** @return LazyCollection<int, Sale> */
    public function salesForExport(CarbonInterface $from, CarbonInterface $to, ?int $stockLocationId = null): LazyCollection
    {
        return Sale::query()->completed()->revenue()
            ->with('customer')
            ->whereBetween('sold_at', [$from, $to])
            ->when($stockLocationId, fn ($q) => $q->where('stock_location_id', $stockLocationId))
            ->orderBy('sold_at')
            ->lazy();
    }

    public function countForExport(CarbonInterface $from, CarbonInterface $to, ?int $stockLocationId = null): int
    {
        return Sale::query()->completed()->revenue()
            ->whereBetween('sold_at', [$from, $to])
            ->when($stockLocationId, fn ($q) => $q->where('stock_location_id', $stockLocationId))
            ->count();
    }
}
