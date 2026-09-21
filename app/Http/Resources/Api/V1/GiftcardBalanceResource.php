<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Giftcards\Models\Giftcard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Giftcard */
class GiftcardBalanceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'number' => $this->number,
            'balance' => (string) $this->balance->getAmount(),
            'is_active' => $this->is_active,
            'expires_at' => $this->expires_at?->toDateString(),
        ];
    }
}
