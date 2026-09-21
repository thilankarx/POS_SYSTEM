<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Catalog\Models\ItemKit;
use App\Domain\Identity\Models\User;

class ItemKitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('item_kits.view');
    }

    public function view(User $user, ItemKit $itemKit): bool
    {
        return $user->can('item_kits.view');
    }

    public function create(User $user): bool
    {
        return $user->can('item_kits.manage');
    }

    public function update(User $user, ItemKit $itemKit): bool
    {
        return $user->can('item_kits.manage');
    }

    public function delete(User $user, ItemKit $itemKit): bool
    {
        return $user->can('item_kits.manage');
    }
}
