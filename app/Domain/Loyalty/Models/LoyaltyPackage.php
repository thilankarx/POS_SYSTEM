<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Models;

use App\Domain\Crm\Models\Customer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoyaltyPackage extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'points_per_currency_unit' => 'decimal:4',
            'currency_value_per_point' => 'decimal:4',
            'is_active' => 'boolean',
            'points_expire_after_days' => 'integer',
        ];
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }
}
