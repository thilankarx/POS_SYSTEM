<?php

declare(strict_types=1);

namespace App\Domain\Sales\Models;

use App\Domain\Taxation\Models\TaxRate;
use App\Support\Money\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleLineTax extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'taxable_amount' => MoneyCast::class,
            'tax_amount' => MoneyCast::class,
            'is_inclusive' => 'boolean',
        ];
    }

    public function saleLine(): BelongsTo
    {
        return $this->belongsTo(SaleLine::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }
}
