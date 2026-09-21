<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;

class ItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('items.view');
    }

    public function view(User $user, Item $item): bool
    {
        return $user->can('items.view');
    }

    public function create(User $user): bool
    {
        return $user->can('items.manage');
    }

    public function update(User $user, Item $item): bool
    {
        return $user->can('items.manage');
    }

    public function delete(User $user, Item $item): bool
    {
        return $user->can('items.delete');
    }
}
