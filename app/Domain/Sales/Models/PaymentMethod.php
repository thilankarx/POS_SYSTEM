<?php

declare(strict_types=1);

namespace App\Domain\Sales\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A payment method has a stable `code`; `name` is presentation only.
 *
 * OSPOS wrote the *translated* label into `sales_payments.payment_type`, so an
 * install that switched language produced a mix of "Cash", "Efectivo" and
 * "Especes" in one column and every payment report fragmented.
 */
class PaymentMethod extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'opens_drawer' => 'boolean',
            'allows_change' => 'boolean',
            'allows_refund' => 'boolean',
            'counts_as_cash' => 'boolean',
            'requires_reference' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
