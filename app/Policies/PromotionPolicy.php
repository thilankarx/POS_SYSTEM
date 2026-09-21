<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Promotions\Models\Promotion;

class PromotionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('promotions.view');
    }

    public function view(User $user, Promotion $promotion): bool
    {
        return $user->can('promotions.view');
    }

    public function create(User $user): bool
    {
        return $user->can('promotions.manage');
    }

    public function update(User $user, Promotion $promotion): bool
    {
        return $user->can('promotions.manage');
    }

    public function delete(User $user, Promotion $promotion): bool
    {
        return $user->can('promotions.manage');
    }
}
