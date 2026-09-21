<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Crm\Models\Customer;
use App\Domain\Identity\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('customers.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->can('customers.view');
    }

    public function create(User $user): bool
    {
        return $user->can('customers.manage');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->can('customers.manage');
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->can('customers.delete');
    }
}
