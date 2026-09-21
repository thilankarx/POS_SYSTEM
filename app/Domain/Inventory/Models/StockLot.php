<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use App\Domain\Catalog\Models\Item;
use App\Support\Money\MoneyCast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Batch/lot with optional expiry. New in this system: OSPOS had no lot concept,
 * so recalls and FEFO (first-expired-first-out) picking were impossible.
 */
class StockLot extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'manufactured_on' => 'date',
            'expires_on' => 'date',
            'cost_price' => MoneyCast::class,
            'selling_price' => MoneyCast::class,
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** First-expired-first-out ordering for picking. */
    public function scopeFefo(Builder $query): Builder
    {
        return $query->orderByRaw('expires_on IS NULL, expires_on ASC');
    }

    public function scopeExpiringBefore(Builder $query, \DateTimeInterface $date): Builder
    {
        return $query->whereNotNull('expires_on')->where('expires_on', '<=', $date);
    }

    /**
     * Lots of $item currently holding stock. There is no maintained
     * per-lot balance projection (stock_levels is aggregate-only), so this
     * is derived from the movement ledger on every call.
     */
    public function scopeInStockFor(Builder $query, Item $item): Builder
    {
        return $query->where('item_id', $item->id)
            ->whereRaw('(select coalesce(sum(quantity_delta), 0) from stock_movements where stock_movements.stock_lot_id = stock_lots.id) > 0');
    }

    /** @return \Illuminate\Support\Collection<int, StockLot> */
    public static function activeLotsWithPrices(Item $item): \Illuminate\Support\Collection
    {
        return static::query()->inStockFor($item)->fefo()->get();
    }
}
