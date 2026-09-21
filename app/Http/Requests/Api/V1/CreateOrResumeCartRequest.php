<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateOrResumeCartRequest extends FormRequest
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
            'client_uuid' => ['required', 'uuid'],
            'terminal_id' => ['required', 'integer', Rule::exists('terminals', 'id')],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')],
            // 'return' is deliberately excluded: a return is only ever created by
            // RefundSaleAction against an existing sale (negative money, restocking).
            // A cart completed with sale_type=return would go through
            // CompleteSaleAction's forward-sale path instead -- positive money and
            // a stock *decrement* -- which is the opposite of what a return means.
            'sale_type' => ['nullable', Rule::in(['pos', 'invoice', 'quote', 'work_order'])],
            'dinner_table_id' => ['nullable', 'integer', Rule::exists('dinner_tables', 'id')],
            'reference' => ['nullable', 'string'],
            'comment' => ['nullable', 'string'],
        ];
    }
}
