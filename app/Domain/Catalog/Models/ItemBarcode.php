<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An item may carry several barcodes (retail EAN, case code, supplier code).
 * OSPOS allowed exactly one `item_number`, which forced case codes into
 * duplicate item records.
 */
class ItemBarcode extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'pack_quantity' => 'decimal:3',
            'is_primary' => 'boolean',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
