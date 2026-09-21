<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Loyalty\Models\LoyaltyPackage;

class LoyaltyPackagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('loyalty.view');
    }

    public function view(User $user, LoyaltyPackage $loyaltyPackage): bool
    {
        return $user->can('loyalty.view');
    }

    public function create(User $user): bool
    {
        return $user->can('loyalty.manage');
    }

    public function update(User $user, LoyaltyPackage $loyaltyPackage): bool
    {
        return $user->can('loyalty.manage');
    }

    public function delete(User $user, LoyaltyPackage $loyaltyPackage): bool
    {
        return $user->can('loyalty.manage');
    }
}
