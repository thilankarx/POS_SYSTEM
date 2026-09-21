<?php

declare(strict_types=1);

namespace App\Domain\Promotions\Models;

use App\Domain\Crm\Models\Customer;
use App\Domain\Sales\Models\Sale;
use App\Support\Money\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromotionRedemption extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'discount_amount' => MoneyCast::class,
        ];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
