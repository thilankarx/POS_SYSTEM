<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Sales\Models\CartLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CartLine */
class CartLineResource extends JsonResource
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
            'item_name' => $this->whenLoaded('item', fn () => $this->item->name),
            'sku' => $this->whenLoaded('item', fn () => $this->item->sku),
            'item_kit_id' => $this->item_kit_id,
            'stock_lot_id' => $this->stock_lot_id,
            'serial' => $this->serial,
            'description' => $this->description,
            'quantity' => (string) $this->quantity,
            'unit_price' => (string) $this->unit_price->getAmount(),
            'discount_value' => (string) $this->discount_value,
            'discount_type' => $this->discount_type,
            'price_overridden' => $this->price_overridden,
            'kitchen_sent' => $this->kitchen_sent_at !== null,
            'kitchen_prepared' => $this->kitchen_prepared_at !== null,
        ];
    }
}
