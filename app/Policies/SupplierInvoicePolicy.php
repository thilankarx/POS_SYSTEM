<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Purchasing\Models\SupplierInvoice;

class SupplierInvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('purchasing.view');
    }

    public function view(User $user, SupplierInvoice $supplierInvoice): bool
    {
        return $user->can('purchasing.view');
    }

    public function create(User $user): bool
    {
        return $user->can('purchasing.manage');
    }

    public function update(User $user, SupplierInvoice $supplierInvoice): bool
    {
        return $user->can('purchasing.manage');
    }

    public function approve(User $user, SupplierInvoice $supplierInvoice): bool
    {
        return $user->can('purchasing.approve');
    }
}
