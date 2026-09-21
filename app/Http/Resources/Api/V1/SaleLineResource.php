<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Sales\Models\SaleLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SaleLine */
class SaleLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'line_number' => $this->line_number,
            'item_id' => $this->item_id,
            'item_name' => $this->item_name,
            'sku' => $this->sku,
            'quantity' => (string) $this->quantity,
            'unit_price' => (string) $this->unit_price->getAmount(),
            'discount_amount' => (string) $this->discount_amount->getAmount(),
            'line_subtotal' => (string) $this->line_subtotal->getAmount(),
            'line_tax' => (string) $this->line_tax->getAmount(),
            'line_total' => (string) $this->line_total->getAmount(),
        ];
    }
}
