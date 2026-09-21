<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Crm\Models\Supplier;
use App\Domain\Inventory\Models\SerialNumber;
use App\Domain\Inventory\Models\StockLevel;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Models\StockLot;
use App\Domain\Taxation\Models\TaxCategory;
use App\Support\Money\MoneyCast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Item extends Model
{
    use SoftDeletes;

    public const STOCK_TYPE_STOCKED = 'stocked';

    public const STOCK_TYPE_SERVICE = 'service';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            // Only meaningful for a non-stocked item (service/amount-entry)
            // -- a stocked item's price always lives on its stock lot.
            'unit_price' => MoneyCast::class,
            'reorder_level' => 'decimal:3',
            'reorder_quantity' => 'decimal:3',
            'qty_per_pack' => 'decimal:3',
            'is_serialized' => 'boolean',
            'has_expiry' => 'boolean',
            'allow_alt_description' => 'boolean',
            'is_active' => 'boolean',
            'business_types' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function taxCategory(): BelongsTo
    {
        return $this->belongsTo(TaxCategory::class);
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(ItemBarcode::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(StockLot::class);
    }

    public function serialNumbers(): HasMany
    {
        return $this->hasMany(SerialNumber::class);
    }

    public function attributeLinks(): MorphMany
    {
        return $this->morphMany(AttributeLink::class, 'attributable');
    }

    /** Only stocked items move inventory; services and amount-entry lines do not. */
    public function movesStock(): bool
    {
        return $this->stock_type === self::STOCK_TYPE_STOCKED;
    }

    public function quantityAt(StockLocation|int $location): string
    {
        $id = $location instanceof StockLocation ? $location->id : $location;

        return $this->stockLevels->firstWhere('stock_location_id', $id)?->quantity ?? '0.000';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Restrict to items sold by a given business type. An item with no
     * explicit `business_types` list (NULL or []) is sold everywhere and
     * always passes; one with a list passes only if it contains $type.
     * A null/blank $type disables the filter (used for "All types" views).
     */
    public function scopeForBusinessType(Builder $query, ?string $type): Builder
    {
        if ($type === null || $type === '') {
            return $query;
        }

        return $query->where(function (Builder $inner) use ($type) {
            $inner->whereNull('business_types')
                ->orWhereJsonLength('business_types', 0)
                ->orWhereJsonContains('business_types', $type);
        });
    }

    /** The in-memory counterpart of scopeForBusinessType(), for single-item checks. */
    public function soldByBusinessType(?string $type): bool
    {
        $types = $this->business_types ?? [];

        return $type === null || $type === '' || $types === [] || in_array($type, $types, true);
    }

    /**
     * Adds a `has_multiple_prices` column: true when this item currently has
     * 2+ in-stock lots that resolve to genuinely different selling prices
     * (old stock priced differently from a newer batch). Computed in SQL,
     * not per-row in PHP, so listing a whole page of items stays a single
     * query instead of N+1 -- this is used on every catalog refresh. There is
     * no item-level fallback price to coalesce to any more (lot pricing is
     * stocked-items-only) -- an unpriced lot must still count as its own
     * distinct value next to a priced one (via the '__NULL__' sentinel,
     * since COUNT(DISTINCT) silently ignores real NULLs), since a lot with
     * no selling_price sitting next to one that has one is exactly the
     * ambiguity this flag exists to surface.
     */
    public function scopeWithHasMultiplePrices(Builder $query): Builder
    {
        return $query->addSelect([
            'items.*',
            DB::raw("(
                select count(distinct coalesce(cast(stock_lots.selling_price as char), '__NULL__'))
                from stock_lots
                where stock_lots.item_id = items.id
                  and (select coalesce(sum(stock_movements.quantity_delta), 0) from stock_movements where stock_movements.stock_lot_id = stock_lots.id) > 0
            ) > 1 as has_multiple_prices"),
        ]);
    }

    /**
     * Same answer as the `has_multiple_prices` column above, for the single-
     * item lookups (e.g. barcode scan) that don't run scopeWithHasMultiplePrices --
     * reuses the bulk-computed column when it's already been selected.
     */
    public function hasMultiplePrices(): bool
    {
        if (array_key_exists('has_multiple_prices', $this->attributes)) {
            return (bool) $this->attributes['has_multiple_prices'];
        }

        $prices = StockLot::activeLotsWithPrices($this)
            ->map(fn (StockLot $lot) => $lot->selling_price !== null ? (string) $lot->selling_price->getAmount() : null)
            ->unique();

        return $prices->count() > 1;
    }

    /**
     * Adds a `current_price` column: a stocked item's FEFO in-stock lot's
     * selling_price (the same lot AddCartLineAction would auto-assign), or
     * null when it has none yet ("no price yet"); a non-stocked item (which
     * never has lots) falls straight through to its own unit_price. Same
     * single-query-per-page rationale as scopeWithHasMultiplePrices().
     */
    public function scopeWithCurrentPrice(Builder $query): Builder
    {
        return $query->addSelect([
            'items.*',
            DB::raw('coalesce((
                select stock_lots.selling_price
                from stock_lots
                where stock_lots.item_id = items.id
                  and (select coalesce(sum(stock_movements.quantity_delta), 0) from stock_movements where stock_movements.stock_lot_id = stock_lots.id) > 0
                order by stock_lots.expires_on is null, stock_lots.expires_on asc
                limit 1
            ), items.unit_price) as current_price'),
        ]);
    }

    /**
     * Single-item counterpart of scopeWithCurrentPrice(), for lookups that
     * don't run the bulk scope -- same bulk-vs-single duality as
     * hasMultiplePrices() above.
     */
    public function currentPrice(): ?\Brick\Money\Money
    {
        if (array_key_exists('current_price', $this->attributes)) {
            return $this->attributes['current_price'] !== null ? \App\Support\Money\Money::of($this->attributes['current_price']) : null;
        }

        $lot = StockLot::activeLotsWithPrices($this)->first();

        return $lot?->selling_price ?? $this->unit_price;
    }
}
