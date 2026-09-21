<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Finance\Models\Expense;
use App\Domain\Identity\Models\User;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('expenses.view');
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->can('expenses.view');
    }

    public function create(User $user): bool
    {
        return $user->can('expenses.manage');
    }

    public function update(User $user, Expense $expense): bool
    {
        return $user->can('expenses.manage');
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $user->can('expenses.manage');
    }
}
