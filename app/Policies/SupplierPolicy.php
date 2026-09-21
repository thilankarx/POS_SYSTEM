<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Crm\Models\Supplier;
use App\Domain\Identity\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('suppliers.view');
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $user->can('suppliers.view');
    }

    public function create(User $user): bool
    {
        return $user->can('suppliers.manage');
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->can('suppliers.manage');
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->can('suppliers.manage');
    }
}
