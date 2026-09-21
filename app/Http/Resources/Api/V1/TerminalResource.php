<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domain\Sales\Models\Terminal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Terminal */
class TerminalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'stock_location_id' => $this->stock_location_id,
            'stock_location_name' => $this->whenLoaded('stockLocation', fn () => $this->stockLocation->name),
            'has_printer' => $this->hasPrinterConfigured(),
            'shift_open' => $this->openShift() !== null,
        ];
    }
}
