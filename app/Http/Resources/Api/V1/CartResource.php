<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Sales\CartPricer;
use App\Domain\Sales\Models\Cart;
use App\Support\Money\Money as MoneySupport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Cart */
class CartResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $totals = app(CartPricer::class)->price($this->resource);

        return [
            'id' => $this->id,
            'client_uuid' => $this->client_uuid,
            'status' => $this->status,
            'sale_type' => $this->sale_type,
            'terminal_id' => $this->terminal_id,
            'stock_location_id' => $this->stock_location_id,
            'customer_id' => $this->customer_id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'coupon_code' => $this->coupon_code,
            'dinner_table_id' => $this->dinner_table_id,
            'dinner_table' => $this->whenLoaded('dinnerTable', fn () => $this->dinnerTable ? [
                'id' => $this->dinnerTable->id,
                'name' => $this->dinnerTable->name,
            ] : null),
            'reference' => $this->reference,
            'comment' => $this->comment,
            // MoneySupport::of() rather than $this->tip_amount->getAmount()
            // directly -- a freshly Cart::create()d instance that omitted
            // tip_amount would still have it null in-memory here even
            // though the column's DB default is 0 (Eloquent never
            // backfills a default onto the object that triggered it).
            'tip_amount' => (string) MoneySupport::of($this->tip_amount)->getAmount(),
            'lines' => CartLineResource::collection($this->whenLoaded('lines')),
            'payments' => CartPaymentResource::collection($this->whenLoaded('payments')),
            'totals' => [
                'subtotal' => (string) $totals->subtotal->getAmount(),
                'discount_total' => (string) $totals->discountTotal->getAmount(),
                'tax_total' => (string) $totals->taxTotal->getAmount(),
                'rounding_adjustment' => (string) $totals->roundingAdjustment->getAmount(),
                'total' => (string) $totals->total->getAmount(),
            ],
            'currency' => MoneySupport::currency(),
            'created_at' => $this->created_at,
        ];
    }
}
