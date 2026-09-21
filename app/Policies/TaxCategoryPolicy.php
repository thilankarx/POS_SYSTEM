<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Taxation\Models\TaxCategory;

class TaxCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('taxes.manage');
    }

    public function view(User $user, TaxCategory $taxCategory): bool
    {
        return $user->can('taxes.manage');
    }

    public function create(User $user): bool
    {
        return $user->can('taxes.manage');
    }

    public function update(User $user, TaxCategory $taxCategory): bool
    {
        return $user->can('taxes.manage');
    }

    public function delete(User $user, TaxCategory $taxCategory): bool
    {
        return $user->can('taxes.manage');
    }
}
