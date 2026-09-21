<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Identity\Models\User;
use App\Domain\Sales\Models\Cart;

class CartPolicy
{
    public function view(User $user, Cart $cart): bool
    {
        return $user->id === $cart->user_id || $user->canOperateAt($cart->stock_location_id);
    }
}
