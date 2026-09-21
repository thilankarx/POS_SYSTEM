<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Models;

use App\Domain\Crm\Models\Supplier;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Support\Money\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * OSPOS modelled only goods arriving (`receivings`) — no order, no expected
 * delivery, no partial receipt against an order. This is the order half of
 * that loop.
 */
class PurchaseOrder extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PARTIALLY_RECEIVED = 'partially_received';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_CANCELLED = 'cancelled';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'subtotal' => MoneyCast::class,
            'tax_total' => MoneyCast::class,
            'total' => MoneyCast::class,
            'expected_on' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class)->orderBy('line_number');
    }

    public function receivings(): HasMany
    {
        return $this->hasMany(Receiving::class);
    }

    public function nextLineNumber(): int
    {
        return (int) $this->lines()->max('line_number') + 1;
    }

    /** Ordered minus already-received, floor at zero. Never negative even on over-receipt. */
    public function remainingQuantityFor(PurchaseOrderLine $line): string
    {
        $remaining = bcsub((string) $line->quantity_ordered, (string) $line->quantity_received, 3);

        return bccomp($remaining, '0', 3) > 0 ? $remaining : '0.000';
    }

    public function isFullyReceived(): bool
    {
        return $this->lines->every(fn (PurchaseOrderLine $line) => bccomp(
            (string) $line->quantity_received,
            (string) $line->quantity_ordered,
            3
        ) >= 0);
    }
}
