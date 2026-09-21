<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Support\Money\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * The inventory ledger. Append-only: rows are never updated or deleted, so
 * stock history is always reconstructible and `stock_levels` can be rebuilt
 * from it at any time.
 */
class StockMovement extends Model
{
    public const REASON_SALE = 'sale';

    public const REASON_RECEIVING = 'receiving';

    public const REASON_TRANSFER = 'transfer';

    public const REASON_ADJUSTMENT = 'adjustment';

    public const REASON_COUNT = 'count';

    public const REASON_RETURN = 'return';

    public const REASON_RETURN_TO_SUPPLIER = 'return_to_supplier';

    public const REASON_VOID = 'void';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity_delta' => 'decimal:3',
            'balance_after' => 'decimal:3',
            'unit_cost' => MoneyCast::class,
            'occurred_at' => 'datetime',
        ];
    }

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** What caused this movement: a Sale, a Receiving, a StockCount, ... */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
