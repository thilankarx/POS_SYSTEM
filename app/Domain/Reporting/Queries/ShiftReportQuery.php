<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Queries;

use App\Domain\Sales\Models\CashMovement;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\Shift;
use App\Domain\Sales\Models\ShiftCashCount;
use App\Support\Money\Money;
use Brick\Money\Money as BrickMoney;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;

final class ShiftReportQuery
{
    public function list(CarbonInterface $from, CarbonInterface $to, ?int $terminalId = null, ?string $status = null): LengthAwarePaginator
    {
        return Shift::query()
            ->with(['terminal.stockLocation', 'openedBy.person', 'closedBy.person'])
            ->withSum(['sales as sales_total' => fn ($q) => $q->completed()->revenue()], 'total')
            ->withCount(['sales as sales_count' => fn ($q) => $q->completed()->revenue()])
            ->whereBetween('opened_at', [$from, $to])
            ->when($terminalId, fn ($q) => $q->where('terminal_id', $terminalId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest('opened_at')
            ->paginate(20);
    }

    /** @return array{shift_count: int, open_count: int, sale_count: int, sales_total: BrickMoney, counted_cash: BrickMoney, cash_variance: BrickMoney} */
    public function summary(CarbonInterface $from, CarbonInterface $to, ?int $terminalId = null, ?string $status = null): array
    {
        $shiftQuery = Shift::query()
            ->whereBetween('opened_at', [$from, $to])
            ->when($terminalId, fn ($q) => $q->where('terminal_id', $terminalId))
            ->when($status, fn ($q) => $q->where('status', $status));

        $shiftTotals = (clone $shiftQuery)
            ->selectRaw('COUNT(*) as shift_count, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as open_count, COALESCE(SUM(counted_cash), 0) as counted_cash, COALESCE(SUM(cash_variance), 0) as cash_variance', [Shift::STATUS_OPEN])
            ->first();

        $sales = Sale::query()
            ->completed()
            ->revenue()
            ->whereHas('shift', fn ($query) => $query
                ->whereBetween('opened_at', [$from, $to])
                ->when($terminalId, fn ($query) => $query->where('terminal_id', $terminalId))
                ->when($status, fn ($query) => $query->where('status', $status)))
            ->selectRaw('COUNT(*) as sale_count, COALESCE(SUM(total), 0) as total')
            ->first();

        return [
            'shift_count' => (int) $shiftTotals->shift_count,
            'open_count' => (int) $shiftTotals->open_count,
            'sale_count' => (int) $sales->sale_count,
            'sales_total' => $sales->total,
            'counted_cash' => Money::of($shiftTotals->counted_cash),
            'cash_variance' => Money::of($shiftTotals->cash_variance),
        ];
    }

    /** @return Collection<int, ShiftCashCount> */
    public function denominationBreakdown(Shift $shift): Collection
    {
        return $shift->cashCounts()->orderByDesc('denomination')->get();
    }

    /** @return Collection<int, CashMovement> */
    public function cashMovements(Shift $shift): Collection
    {
        return $shift->cashMovements()->with('user')->latest()->get();
    }

    /** @return LazyCollection<int, Shift> */
    public function shiftsForExport(CarbonInterface $from, CarbonInterface $to, ?int $terminalId = null, ?string $status = null): LazyCollection
    {
        return Shift::query()
            ->with(['terminal', 'openedBy'])
            ->withSum(['sales as sales_total' => fn ($q) => $q->completed()->revenue()], 'total')
            ->whereBetween('opened_at', [$from, $to])
            ->when($terminalId, fn ($q) => $q->where('terminal_id', $terminalId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderBy('opened_at')
            ->lazy();
    }

    public function countForExport(CarbonInterface $from, CarbonInterface $to, ?int $terminalId = null, ?string $status = null): int
    {
        return Shift::query()
            ->whereBetween('opened_at', [$from, $to])
            ->when($terminalId, fn ($q) => $q->where('terminal_id', $terminalId))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->count();
    }
}
