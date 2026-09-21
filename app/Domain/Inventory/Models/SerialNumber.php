<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use App\Domain\Catalog\Models\Item;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A tracked serial. OSPOS stored serials as free text on the sale line, so
 * "which customer has serial X" and "is this serial in stock" were
 * unanswerable.
 */
class SerialNumber extends Model
{
    public const STATUS_IN_STOCK = 'in_stock';

    public const STATUS_SOLD = 'sold';

    protected $guarded = ['id'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class, 'stock_lot_id');
    }
}
