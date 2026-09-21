<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Sales\Events\ShiftOpened;
use App\Domain\Sales\Exceptions\ShiftException;
use App\Domain\Sales\Models\Shift;
use App\Domain\Sales\Models\Terminal;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class OpenShiftAction
{
    public function execute(Terminal $terminal, User $user, string $openingFloat, ?string $note = null): Shift
    {
        if (! $terminal->is_active) {
            throw ShiftException::terminalInactive();
        }

        if (! $user->canOperateAt($terminal->stock_location_id)) {
            throw ShiftException::unauthorizedTerminal();
        }

        try {
            return DB::transaction(function () use ($terminal, $user, $openingFloat, $note) {
                $existing = Shift::where('terminal_id', $terminal->id)
                    ->where('status', Shift::STATUS_OPEN)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    throw ShiftException::alreadyOpen();
                }

                $shift = Shift::create([
                    'terminal_id' => $terminal->id,
                    'opened_by_user_id' => $user->id,
                    'opening_float' => $openingFloat,
                    'status' => Shift::STATUS_OPEN,
                    'opened_at' => now(),
                    'note' => $note,
                ]);

                ShiftOpened::dispatch($shift);

                return $shift;
            });
        } catch (UniqueConstraintViolationException) {
            // Backstop for the lockForUpdate() check above: on a first-use
            // row (nothing yet to lock) that check only ever takes a gap
            // lock, which two concurrent opens can both pass. The
            // shifts_one_open_per_terminal DB constraint is what actually
            // stops the second insert -- surfaced as the same clean
            // "already open" error instead of a raw 500.
            throw ShiftException::alreadyOpen();
        }
    }
}
