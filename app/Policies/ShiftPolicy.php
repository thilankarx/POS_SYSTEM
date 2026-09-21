<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Sales\Models\Shift;
use App\Domain\Sales\Models\Terminal;

class ShiftPolicy
{
    public function open(User $user, Terminal $terminal): bool
    {
        return $user->can('shifts.open')
            && $terminal->is_active
            && $terminal->openShift() === null
            && $user->canOperateAt($terminal->stock_location_id);
    }

    public function close(User $user, Shift $shift): bool
    {
        return $user->can('shifts.close')
            && ($shift->opened_by_user_id === $user->id || $user->can('shifts.view_all'));
    }

    public function viewAny(User $user): bool
    {
        return $user->can('shifts.view_all');
    }
}
