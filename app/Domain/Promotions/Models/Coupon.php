<?php

declare(strict_types=1);

namespace App\Domain\Promotions\Models;

use App\Domain\Crm\Models\Customer;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Coupon extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isRedeemable(?int $customerId, CarbonInterface $at): bool
    {
        if ($this->use_count >= $this->max_uses) {
            return false;
        }

        if ($this->expires_at !== null && $at->gt($this->expires_at)) {
            return false;
        }

        if ($this->customer_id !== null && $this->customer_id !== $customerId) {
            return false;
        }

        return true;
    }
}
