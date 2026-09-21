<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Queries;

use App\Domain\Identity\Models\User;
use App\Domain\Sales\Models\Sale;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class CommissionReportQuery
{
    /** One row per waiter with a commission-earning sale in range, sorted by name. */
    public function summary(CarbonInterface $from, CarbonInterface $to): Collection
    {
        return User::query()
            ->whereHas('waiterSales', fn (Builder $q) => $this->scopeSales($q, $from, $to))
            ->withSum(['waiterSales as total_commission' => fn (Builder $q) => $this->scopeSales($q, $from, $to)], 'commission_amount')
            // Tips are never signed/reversed on a return (see the
            // migration's docblock), so summing across pos/invoice/return
            // rows is safe -- a return row just contributes zero.
            ->withSum(['waiterSales as total_tips' => fn (Builder $q) => $this->scopeSales($q, $from, $to)], 'tip_amount')
            ->withCount(['waiterSales as sale_count' => fn (Builder $q) => $this->scopeSales($q, $from, $to)->where('sale_type', '!=', Sale::TYPE_RETURN)])
            ->with('person')
            ->get()
            ->sortBy('name')
            ->values();
    }

    /** The individual sales (and any returns against them) behind one waiter's total, for drill-down. */
    public function salesFor(int $waiterId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $this->scopeSales(Sale::where('waiter_id', $waiterId), $from, $to)
            ->orderBy('sold_at')
            ->get();
    }

    /**
     * Revenue sales (pos/invoice/return -- never a quote/work order) that
     * were never voided, in range. A voided sale earns no commission at
     * all; a refund's own negative commission_amount already nets against
     * the original with no special-casing needed here (see
     * RefundSaleAction's docblock on why every return field is negative).
     */
    private function scopeSales(Builder $query, CarbonInterface $from, CarbonInterface $to): Builder
    {
        return $query->revenue()
            ->where('status', '!=', Sale::STATUS_VOIDED)
            ->whereBetween('sold_at', [$from, $to]);
    }
}
