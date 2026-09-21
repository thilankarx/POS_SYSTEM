<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Queries;

use App\Domain\Sales\Models\Payment;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

final class PaymentReportQuery
{
    public function payments(
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $paymentMethodId = null,
        ?int $stockLocationId = null,
    ): LengthAwarePaginator {
        return $this->filtered($from, $to, $paymentMethodId, $stockLocationId)
            ->with(['sale', 'method'])
            ->latest('payments.created_at')
            ->paginate(50);
    }

    /** @return Collection<int, object> */
    public function summaryByMethod(CarbonInterface $from, CarbonInterface $to, ?int $stockLocationId = null): Collection
    {
        return Payment::query()
            ->join('sales', 'sales.id', '=', 'payments.sale_id')
            ->join('payment_methods', 'payment_methods.id', '=', 'payments.payment_method_id')
            ->where('payments.status', Payment::STATUS_CAPTURED)
            ->whereBetween('sales.sold_at', [$from, $to])
            ->when($stockLocationId, fn ($q) => $q->where('sales.stock_location_id', $stockLocationId))
            ->selectRaw('payment_methods.name as method_name, COUNT(*) as payment_count, SUM(payments.amount) as amount')
            ->groupBy('method_name')
            ->orderByDesc('amount')
            ->get();
    }

    /** @return LazyCollection<int, Payment> */
    public function paymentsForExport(
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $paymentMethodId = null,
        ?int $stockLocationId = null,
    ): LazyCollection {
        return $this->filtered($from, $to, $paymentMethodId, $stockLocationId)
            ->with(['sale', 'method'])
            ->orderBy('payments.created_at')
            ->lazy();
    }

    public function countForExport(
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $paymentMethodId = null,
        ?int $stockLocationId = null,
    ): int {
        return $this->filtered($from, $to, $paymentMethodId, $stockLocationId)->count();
    }

    private function filtered(
        CarbonInterface $from,
        CarbonInterface $to,
        ?int $paymentMethodId,
        ?int $stockLocationId,
    ) {
        return Payment::query()
            ->join('sales', 'sales.id', '=', 'payments.sale_id')
            ->where('payments.status', Payment::STATUS_CAPTURED)
            ->whereBetween('sales.sold_at', [$from, $to])
            ->when($paymentMethodId, fn ($q) => $q->where('payments.payment_method_id', $paymentMethodId))
            ->when($stockLocationId, fn ($q) => $q->where('sales.stock_location_id', $stockLocationId))
            ->select('payments.*');
    }
}
