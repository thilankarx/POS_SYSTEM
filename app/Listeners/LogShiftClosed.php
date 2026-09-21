<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Sales\Events\ShiftClosed;

final class LogShiftClosed
{
    public function handle(ShiftClosed $event): void
    {
        $shift = $event->shift;

        activity('shift')
            ->performedOn($shift)
            ->causedBy($shift->closedBy)
            ->event('shift_closed')
            ->withProperties([
                'terminal_id' => $shift->terminal_id,
                'expected_cash' => (string) $shift->expected_cash,
                'counted_cash' => (string) $shift->counted_cash,
                'cash_variance' => (string) $shift->cash_variance,
            ])
            ->log("Shift closed on {$shift->terminal?->name}: variance {$shift->cash_variance}.");
    }
}
