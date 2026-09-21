<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockCount;

class StockCountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('inventory.view');
    }

    public function view(User $user, StockCount $stockCount): bool
    {
        return $user->can('inventory.view');
    }

    public function create(User $user): bool
    {
        return $user->can('inventory.count');
    }

    public function update(User $user, StockCount $stockCount): bool
    {
        return $user->can('inventory.count');
    }

    public function approve(User $user, StockCount $stockCount): bool
    {
        return $user->can('inventory.count_approve');
    }

    public function unapprove(User $user, StockCount $stockCount): bool
    {
        return $user->can('inventory.count_approve');
    }
}
