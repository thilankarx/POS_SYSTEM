<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Catalog\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Item */
class ItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentPrice = $this->currentPrice();

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'unit_price' => $currentPrice !== null ? (string) $currentPrice->getAmount() : null,
            'tax_category_id' => $this->tax_category_id,
            'stock_type' => $this->stock_type,
            'moves_stock' => $this->movesStock(),
            'has_multiple_prices' => $this->hasMultiplePrices(),
            'is_serialized' => $this->is_serialized,
            'category_id' => $this->category_id,
            'business_types' => $this->business_types ?? [],
            'is_active' => $this->is_active,
            'barcodes' => $this->whenLoaded('barcodes', fn () => $this->barcodes->pluck('barcode')->values()),
            'stock_at_location' => $this->when(
                $request->filled('stock_location_id') && $this->relationLoaded('stockLevels'),
                fn () => $this->quantityAt((int) $request->integer('stock_location_id')),
            ),
        ];
    }
}
