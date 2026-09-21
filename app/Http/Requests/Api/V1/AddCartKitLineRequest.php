<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddCartKitLineRequest extends FormRequest
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
            'item_kit_id' => ['required', 'integer', Rule::exists('item_kits', 'id')],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
        ];
    }
}
