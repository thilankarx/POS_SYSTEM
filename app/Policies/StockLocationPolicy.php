<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;

class StockLocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('locations.view');
    }

    public function view(User $user, StockLocation $stockLocation): bool
    {
        return $user->can('locations.view');
    }

    public function create(User $user): bool
    {
        return $user->can('locations.manage');
    }

    public function update(User $user, StockLocation $stockLocation): bool
    {
        return $user->can('locations.manage');
    }

    public function delete(User $user, StockLocation $stockLocation): bool
    {
        return $user->can('locations.manage');
    }

    public function viewStock(User $user, StockLocation $stockLocation): bool
    {
        // Unlike view()/update()/delete() above (managing the list of
        // locations, legitimately an admin-wide concern), viewStock() is an
        // everyday operational screen (stock levels at one specific
        // location) that every inventory.view holder -- including a
        // Cashier -- could otherwise browse for any warehouse, whether or
        // not they have any relationship to it.
        return $user->can('inventory.view') && $user->canOperateAt($stockLocation);
    }
}
