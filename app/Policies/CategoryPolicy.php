<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Catalog\Models\Category;
use App\Domain\Identity\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('items.view');
    }

    public function view(User $user, Category $category): bool
    {
        return $user->can('items.view');
    }

    public function create(User $user): bool
    {
        return $user->can('items.manage');
    }

    public function update(User $user, Category $category): bool
    {
        return $user->can('items.manage');
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->can('items.manage');
    }
}
