<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domain\Sales\Events\ShiftOpened;

final class LogShiftOpened
{
    public function handle(ShiftOpened $event): void
    {
        $shift = $event->shift;

        activity('shift')
            ->performedOn($shift)
            ->causedBy($shift->openedBy)
            ->event('shift_opened')
            ->withProperties([
                'terminal_id' => $shift->terminal_id,
                'opening_float' => (string) $shift->opening_float,
            ])
            ->log("Shift opened on {$shift->terminal?->name} with a {$shift->opening_float} float.");
    }
}
