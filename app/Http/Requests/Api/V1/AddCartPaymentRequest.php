<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddCartPaymentRequest extends FormRequest
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
            'payment_method_id' => ['required', 'integer', Rule::exists('payment_methods', 'id')],
            // The upper bound is a sanity ceiling, not a due-total cap --
            // change-making legitimately means amount/tendered can exceed
            // what's due (paying a $3 coffee with a $100 bill is normal).
            // It exists only to reject a wildly disproportionate value
            // (an amount several orders of magnitude past any plausible
            // cash tender) that would otherwise be booked as real
            // paid_total/change_given and corrupt shift and sales reports.
            'amount' => ['required', 'numeric', 'gt:0', 'lte:1000000'],
            'tendered' => ['nullable', 'numeric', 'gte:0', 'lte:1000000'],
            'reference' => ['nullable', 'string'],
        ];
    }
}
