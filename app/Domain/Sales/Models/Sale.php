<?php

declare(strict_types=1);

namespace App\Domain\Sales\Models;

use App\Domain\Crm\Models\Customer;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Models\StockMovement;
use App\Support\Money\MoneyCast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Sale extends Model
{
    use LogsActivity;

    public const TYPE_POS = 'pos';

    public const TYPE_INVOICE = 'invoice';

    public const TYPE_QUOTE = 'quote';

    public const TYPE_WORK_ORDER = 'work_order';

    public const TYPE_RETURN = 'return';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_VOIDED = 'voided';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'subtotal' => MoneyCast::class,
            'discount_total' => MoneyCast::class,
            'tax_total' => MoneyCast::class,
            'rounding_adjustment' => MoneyCast::class,
            'total' => MoneyCast::class,
            'paid_total' => MoneyCast::class,
            'change_given' => MoneyCast::class,
            'cost_total' => MoneyCast::class,
            'tip_amount' => MoneyCast::class,
            'commission_rate_applied' => 'decimal:2',
            'commission_amount' => MoneyCast::class,
            'sold_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /** Voids, refunds and price overrides must be attributable. */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'total', 'paid_total', 'returns_sale_id', 'voided_by_user_id', 'void_reason'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SaleLine::class)->orderBy('line_number');
    }

    public function taxes(): HasMany
    {
        return $this->hasMany(SaleTax::class)->orderBy('print_sequence');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Whoever took the order this sale (or, for a return, the original sale) came from. */
    public function waiter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class);
    }

    /** The sale this one reverses, when it is a return. */
    public function returnsSale(): BelongsTo
    {
        return $this->belongsTo(self::class, 'returns_sale_id');
    }

    public function returns(): HasMany
    {
        return $this->hasMany(self::class, 'returns_sale_id');
    }

    public function returnReason(): BelongsTo
    {
        return $this->belongsTo(ReturnReason::class);
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_user_id');
    }

    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'source');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /** Quotes and work orders are not revenue and must never reach a sales report. */
    public function scopeRevenue(Builder $query): Builder
    {
        return $query->whereIn('sale_type', [self::TYPE_POS, self::TYPE_INVOICE, self::TYPE_RETURN]);
    }
}
