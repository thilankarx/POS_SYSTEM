<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Catalog\Models\ItemKit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ItemKit */
class ItemKitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kit_number' => $this->kit_number,
            'name' => $this->name,
            'description' => $this->description,
            'discount_value' => (string) $this->discount_value,
            'discount_type' => $this->discount_type,
            'price_option' => $this->price_option,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'item_id' => $item->id,
                'quantity' => (string) $item->pivot->quantity,
            ])->values()),
        ];
    }
}
