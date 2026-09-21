<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Identity\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function view(User $user, User $target): bool
    {
        return $user->can('users.view');
    }

    public function create(User $user): bool
    {
        return $user->can('users.manage');
    }

    public function update(User $user, User $target): bool
    {
        return $user->can('users.manage');
    }

    public function delete(User $user, User $target): bool
    {
        return $user->can('users.manage');
    }

    /**
     * False when removing the Owner role from, or deactivating, this user
     * would leave the system with no active Owner.
     */
    public function canRemoveOwnerRole(User $actor, User $target): bool
    {
        if (! $target->hasRole('Owner')) {
            return true;
        }

        $otherActiveOwners = User::role('Owner')
            ->where('is_active', true)
            ->whereKeyNot($target->id)
            ->exists();

        return $otherActiveOwners;
    }
}
