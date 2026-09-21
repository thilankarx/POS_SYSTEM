<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Sales\Models\Sale;

class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('sales.view');
    }

    public function view(User $user, Sale $sale): bool
    {
        return $user->can('sales.view') && $user->canOperateAt($sale->stock_location_id);
    }

    public function refund(User $user, Sale $sale): bool
    {
        return $user->can('sales.refund') && $user->canOperateAt($sale->stock_location_id);
    }

    public function void(User $user, Sale $sale): bool
    {
        return $user->can('sales.void') && $user->canOperateAt($sale->stock_location_id);
    }
}
