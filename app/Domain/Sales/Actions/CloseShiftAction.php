<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Sales\Events\ShiftClosed;
use App\Domain\Sales\Exceptions\ShiftException;
use App\Domain\Sales\Models\Payment;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\Shift;
use App\Support\Money\Money as MoneySupport;
use Brick\Math\RoundingMode;
use Brick\Money\Money as BrickMoney;
use Illuminate\Support\Facades\DB;

final class CloseShiftAction
{
    /**
     * @param  array<int, array{denomination: string, count: int}>  $cashCounts
     */
    public function execute(Shift $shift, User $closedBy, array $cashCounts, ?string $countedNonCash = null, ?string $note = null): Shift
    {
        return DB::transaction(function () use ($shift, $closedBy, $cashCounts, $countedNonCash, $note) {
            $locked = Shift::whereKey($shift->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                throw ShiftException::notOpen();
            }

            $expectedCash = $this->expectedCash($locked);
            $cashDropped = $this->cashDropped($locked);

            $counted = MoneySupport::zero();
            foreach ($cashCounts as $row) {
                $subtotal = MoneySupport::of($row['denomination'])->multipliedBy($row['count'], RoundingMode::HalfUp);
                $counted = $counted->plus($subtotal);

                $locked->cashCounts()->create([
                    'denomination' => $row['denomination'],
                    'count' => $row['count'],
                    'subtotal' => $subtotal,
                ]);
            }

            $variance = $counted->minus($expectedCash);

            $locked->update([
                'status' => Shift::STATUS_CLOSED,
                'closed_by_user_id' => $closedBy->id,
                'closed_at' => now(),
                'expected_cash' => $expectedCash,
                'counted_cash' => $counted,
                'cash_variance' => $variance,
                'cash_dropped' => $cashDropped,
                'counted_non_cash' => $countedNonCash,
                'note' => $note,
            ]);

            $locked->refresh();

            ShiftClosed::dispatch($locked);

            return $locked;
        });
    }

    /**
     * Opening float, plus cash sales taken during the shift, minus change
     * handed back on those sales, plus cash movements in, minus cash
     * movements out.
     */
    private function expectedCash(Shift $shift): BrickMoney
    {
        $cashSales = MoneySupport::of((string) (DB::table('payments')
            ->join('sales', 'sales.id', '=', 'payments.sale_id')
            ->join('payment_methods', 'payment_methods.id', '=', 'payments.payment_method_id')
            ->where('sales.shift_id', $shift->id)
            ->where('payment_methods.counts_as_cash', true)
            ->where('payments.status', Payment::STATUS_CAPTURED)
            ->sum('payments.amount') ?: '0'));

        // Summed once per sale (not joined through payments, which would
        // multiply it for a sale with more than one captured payment).
        // Voided sales are excluded: their payment is already excluded from
        // $cashSales above, so the change that was handed back before the
        // void must not be subtracted a second time on top of that -- a
        // void is a full undo, net zero against the drawer either way.
        $changeGiven = MoneySupport::of((string) (DB::table('sales')
            ->where('shift_id', $shift->id)
            ->where('status', '!=', Sale::STATUS_VOIDED)
            ->sum('change_given') ?: '0'));

        $movementsIn = MoneySupport::of((string) (DB::table('cash_movements')
            ->where('shift_id', $shift->id)
            ->where('direction', 'in')
            ->sum('amount') ?: '0'));

        $movementsOut = MoneySupport::of((string) (DB::table('cash_movements')
            ->where('shift_id', $shift->id)
            ->where('direction', 'out')
            ->sum('amount') ?: '0'));

        return MoneySupport::of($shift->opening_float)
            ->plus($cashSales)
            ->minus($changeGiven)
            ->plus($movementsIn)
            ->minus($movementsOut);
    }

    /** Total cash removed from the drawer this shift via a paid-out movement. */
    private function cashDropped(Shift $shift): BrickMoney
    {
        return MoneySupport::of((string) (DB::table('cash_movements')
            ->where('shift_id', $shift->id)
            ->where('direction', 'out')
            ->sum('amount') ?: '0'));
    }
}
