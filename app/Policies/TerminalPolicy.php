<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Sales\Models\Terminal;

class TerminalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('terminals.manage');
    }

    public function view(User $user, Terminal $terminal): bool
    {
        return $user->can('terminals.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('terminals.manage');
    }

    public function update(User $user, Terminal $terminal): bool
    {
        return $user->can('terminals.manage');
    }

    public function delete(User $user, Terminal $terminal): bool
    {
        return $user->can('terminals.manage');
    }
}
