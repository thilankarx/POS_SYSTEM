<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddCartLineRequest extends FormRequest
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
            'item_id' => ['required', 'integer', Rule::exists('items', 'id')],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'stock_lot_id' => ['nullable', 'integer', Rule::exists('stock_lots', 'id')],
            'description' => ['nullable', 'string'],
        ];
    }
}
