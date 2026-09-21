<?php

declare(strict_types=1);

namespace App\Domain\Giftcards\Models;

use App\Domain\Sales\Models\Sale;
use App\Support\Money\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GiftcardTransaction extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class,
            'balance_after' => MoneyCast::class,
        ];
    }

    public function giftcard(): BelongsTo
    {
        return $this->belongsTo(Giftcard::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
