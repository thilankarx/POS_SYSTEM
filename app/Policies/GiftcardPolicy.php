<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Giftcards\Models\Giftcard;
use App\Domain\Identity\Models\User;

class GiftcardPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('giftcards.view');
    }

    public function view(User $user, Giftcard $giftcard): bool
    {
        return $user->can('giftcards.view');
    }

    public function create(User $user): bool
    {
        return $user->can('giftcards.manage');
    }

    public function update(User $user, Giftcard $giftcard): bool
    {
        return $user->can('giftcards.manage');
    }

    public function delete(User $user, Giftcard $giftcard): bool
    {
        return $user->can('giftcards.manage');
    }
}
