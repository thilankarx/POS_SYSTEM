<?php

declare(strict_types=1);

namespace App\Domain\Sales\Models;

use App\Domain\Taxation\Models\TaxRate;
use App\Support\Money\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Per-sale tax summary, as printed. */
class SaleTax extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'taxable_amount' => MoneyCast::class,
            'tax_amount' => MoneyCast::class,
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }
}
