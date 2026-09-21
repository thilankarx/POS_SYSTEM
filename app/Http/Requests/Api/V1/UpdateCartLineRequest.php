<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Support\Money\Rules\ValidDecimal;
use App\Support\Money\Rules\ValidMoneyAmount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCartLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['sometimes', 'numeric', 'gt:0'],
            // unit_price is MoneyCast (2-decimal precision); discount_value
            // below is a plain decimal:4 cast (it can be a percentage rate),
            // so it correctly keeps the looser ValidDecimal.
            'unit_price' => ['sometimes', new ValidMoneyAmount],
            'price_overridden' => ['sometimes', 'boolean'],
            'discount_value' => ['sometimes', new ValidDecimal],
            'discount_type' => ['sometimes', Rule::in(['percent', 'fixed'])],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'serial' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
