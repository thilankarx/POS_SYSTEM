<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Purchasing\Models\Receiving;

class ReceivingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('receivings.view');
    }

    public function view(User $user, Receiving $receiving): bool
    {
        return $user->can('receivings.view');
    }

    public function create(User $user): bool
    {
        return $user->can('receivings.manage');
    }

    public function printLabels(User $user, Receiving $receiving): bool
    {
        return $user->can('receivings.manage');
    }
}
