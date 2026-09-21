<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\Models;

use App\Domain\Crm\Models\Supplier;
use App\Support\Money\Money as MoneySupport;
use App\Support\Money\MoneyCast;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class SupplierInvoice extends Model
{
    use LogsActivity;

    public const STATUS_OPEN = 'open';

    public const STATUS_PARTIALLY_PAID = 'partially_paid';

    public const STATUS_PAID = 'paid';

    public const STATUS_DISPUTED = 'disputed';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'total' => MoneyCast::class,
            'paid_total' => MoneyCast::class,
            'invoice_date' => 'date',
            'due_date' => 'date',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'total', 'paid_total'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /** What has actually arrived against this invoice's PO, in money terms. */
    public function receivedTotal(): Money
    {
        if ($this->purchase_order_id === null) {
            return MoneySupport::zero();
        }

        $sum = Receiving::where('purchase_order_id', $this->purchase_order_id)
            ->where('type', Receiving::TYPE_RECEIPT)
            ->sum('total');

        return MoneySupport::of((string) $sum);
    }
}
