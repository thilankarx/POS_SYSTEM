<?php

declare(strict_types=1);

namespace App\Domain\Giftcards\Models;

use App\Domain\Crm\Models\Customer;
use App\Support\Money\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Giftcard extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'initial_value' => MoneyCast::class,
            'balance' => MoneyCast::class,
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(GiftcardTransaction::class);
    }
}
