<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Sales\Models\CartPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CartPayment */
class CartPaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_method_id' => $this->payment_method_id,
            'method_code' => $this->whenLoaded('method', fn () => $this->method->code),
            'amount' => (string) $this->amount->getAmount(),
            'tendered' => $this->tendered ? (string) $this->tendered->getAmount() : null,
            'reference' => $this->reference,
        ];
    }
}
