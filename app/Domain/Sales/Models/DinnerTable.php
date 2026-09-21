<?php

declare(strict_types=1);

namespace App\Domain\Sales\Models;

use App\Domain\Inventory\Models\StockLocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A dine-in table on a restaurant floor. Status tracks whether an order is currently open against it. */
class DinnerTable extends Model
{
    use SoftDeletes;

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_OCCUPIED = 'occupied';

    protected $guarded = ['id'];

    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class);
    }
}
