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

class Receiving extends Model
{
    public const TYPE_RECEIPT = 'receipt';

    public const TYPE_RETURN_TO_SUPPLIER = 'return_to_supplier';

    public const TYPE_TRANSFER_IN = 'transfer_in';

    public const TYPE_TRANSFER_OUT = 'transfer_out';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'subtotal' => MoneyCast::class,
            'tax_total' => MoneyCast::class,
            'total' => MoneyCast::class,
            'received_at' => 'datetime',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class);
    }

    public function transferToLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'transfer_to_location_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ReceivingLine::class)->orderBy('line_number');
    }

    public function nextLineNumber(): int
    {
        return (int) $this->lines()->max('line_number') + 1;
    }
}
