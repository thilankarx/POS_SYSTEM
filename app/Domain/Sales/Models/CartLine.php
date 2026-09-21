<?php

declare(strict_types=1);

namespace App\Domain\Sales\Models;

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\ItemKit;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLot;
use App\Support\Money\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartLine extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price' => MoneyCast::class,
            'cost_price' => MoneyCast::class,
            'discount_value' => 'decimal:4',
            'price_overridden' => 'boolean',
            'kitchen_sent_at' => 'datetime',
            'kitchen_prepared_at' => 'datetime',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function kit(): BelongsTo
    {
        return $this->belongsTo(ItemKit::class, 'item_kit_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class, 'stock_lot_id');
    }

    public function priceOverriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'price_overridden_by_user_id');
    }
}
