<?php

declare(strict_types=1);

namespace App\Domain\Crm\Models;

use App\Domain\Identity\Models\Person;
use App\Domain\Loyalty\Models\LoyaltyPackage;
use App\Domain\Loyalty\Models\PointsTransaction;
use App\Domain\Sales\Models\Sale;
use App\Domain\Taxation\Models\TaxCategory;
use App\Support\Money\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_tax_exempt' => 'boolean',
            'marketing_consent' => 'boolean',
            'consent_given_at' => 'datetime',
            'credit_limit' => MoneyCast::class,
            'points_balance' => 'decimal:3',
            'discount_value' => 'decimal:4',
        ];
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    public function taxCategory(): BelongsTo
    {
        return $this->belongsTo(TaxCategory::class);
    }

    public function loyaltyPackage(): BelongsTo
    {
        return $this->belongsTo(LoyaltyPackage::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function pointsTransactions(): HasMany
    {
        return $this->hasMany(PointsTransaction::class);
    }
}
