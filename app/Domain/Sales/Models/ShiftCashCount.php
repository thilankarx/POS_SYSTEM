<?php

declare(strict_types=1);

namespace App\Domain\Sales\Models;

use App\Support\Money\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One denomination row of a drawer count. */
class ShiftCashCount extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'denomination' => MoneyCast::class,
            'subtotal' => MoneyCast::class,
        ];
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }
}
