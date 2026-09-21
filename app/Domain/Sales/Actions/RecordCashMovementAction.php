<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Sales\Exceptions\ShiftException;
use App\Domain\Sales\Models\CashMovement;
use App\Domain\Sales\Models\Shift;
use Illuminate\Support\Facades\DB;

final class RecordCashMovementAction
{
    public function execute(Shift $shift, string $direction, string $amount, string $reason, User $user): CashMovement
    {
        return DB::transaction(function () use ($shift, $direction, $amount, $reason, $user) {
            $locked = Shift::whereKey($shift->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isOpen()) {
                throw ShiftException::notOpen();
            }

            return CashMovement::create([
                'shift_id' => $locked->id,
                'user_id' => $user->id,
                'direction' => $direction,
                'amount' => $amount,
                'reason' => $reason,
            ]);
        });
    }
}
