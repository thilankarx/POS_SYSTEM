<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Sales\Models\Sale;
use App\Support\Money\Money as MoneySupport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Sale */
class SaleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'invoice_number' => $this->invoice_number,
            'quote_number' => $this->quote_number,
            'status' => $this->status,
            'sale_type' => $this->sale_type,
            'subtotal' => (string) $this->subtotal->getAmount(),
            'discount_total' => (string) $this->discount_total->getAmount(),
            'tax_total' => (string) $this->tax_total->getAmount(),
            'rounding_adjustment' => (string) $this->rounding_adjustment->getAmount(),
            'total' => (string) $this->total->getAmount(),
            // MoneySupport::of() -- same in-memory-null-before-reload risk
            // as CartResource's tip_amount, guarded the same way.
            'tip_amount' => (string) MoneySupport::of($this->tip_amount)->getAmount(),
            'paid_total' => (string) $this->paid_total->getAmount(),
            'change_given' => (string) $this->change_given->getAmount(),
            'currency' => $this->currency,
            'sold_at' => $this->sold_at,
            'lines' => SaleLineResource::collection($this->whenLoaded('lines')),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($payment) => [
                'method_code' => $payment->method?->code,
                'amount' => (string) $payment->amount->getAmount(),
            ])),
            'taxes' => $this->whenLoaded('taxes', fn () => $this->taxes->map(fn ($tax) => [
                'name' => $tax->name,
                'rate' => (string) $tax->rate,
                'taxable_amount' => (string) $tax->taxable_amount->getAmount(),
                'tax_amount' => (string) $tax->tax_amount->getAmount(),
            ])),
        ];
    }
}
