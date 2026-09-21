<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Sales\Models\DinnerTable;

class DinnerTablePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tables.manage');
    }

    public function view(User $user, DinnerTable $table): bool
    {
        return $user->can('tables.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('tables.manage');
    }

    public function update(User $user, DinnerTable $table): bool
    {
        return $user->can('tables.manage');
    }

    public function delete(User $user, DinnerTable $table): bool
    {
        return $user->can('tables.manage');
    }
}
