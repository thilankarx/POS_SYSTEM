<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'name' => $this->name,
            // Drives client-side routing only (e.g. a kitchen-only login
            // never sees the register) -- every actual register action is
            // still independently gated server-side by the same ability.
            'can_operate_register' => $this->can('sales.create'),
            // Drives client-side UI only (e.g. a waiter never sees the
            // "Take payment"/"Open drawer" buttons or reaches /pay) --
            // payment/drawer routes are still independently gated
            // server-side by sales.checkout.
            'can_checkout' => $this->can('sales.checkout'),
        ];
    }
}
