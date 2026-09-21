<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Sales\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PaymentMethod */
class PaymentMethodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'kind' => $this->kind,
            'provider' => $this->provider,
            'opens_drawer' => $this->opens_drawer,
            'allows_change' => $this->allows_change,
            'requires_reference' => $this->requires_reference,
            'counts_as_cash' => $this->counts_as_cash,
            'sort_order' => $this->sort_order,
        ];
    }
}
