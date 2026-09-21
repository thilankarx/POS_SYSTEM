<?php

declare(strict_types=1);

namespace App\Domain\Sales\Models;

use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLot;
use App\Support\Money\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Carries a snapshot of name/sku/price as sold, so a later catalog edit can
 * never change what a historical receipt says.
 */
class SaleLine extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'quantity_returned' => 'decimal:3',
            'unit_price' => MoneyCast::class,
            'cost_price' => MoneyCast::class,
            'discount_amount' => MoneyCast::class,
            'line_subtotal' => MoneyCast::class,
            'line_tax' => MoneyCast::class,
            'line_total' => MoneyCast::class,
            'discount_value' => 'decimal:4',
            'price_overridden' => 'boolean',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class, 'stock_lot_id');
    }

    public function priceOverriddenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'price_overridden_by_user_id');
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(SaleLineTax::class);
    }

    public function remainingReturnable(): string
    {
        return bcsub((string) $this->quantity, (string) $this->quantity_returned, 3);
    }
}
