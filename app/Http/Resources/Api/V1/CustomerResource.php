<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Crm\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Customer */
class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_name' => $this->company_name,
            'full_name' => $this->person?->full_name,
            'email' => $this->person?->email,
            'phone' => $this->person?->phone,
        ];
    }
}
