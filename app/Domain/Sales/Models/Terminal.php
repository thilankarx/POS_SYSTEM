<?php

declare(strict_types=1);

namespace App\Domain\Sales\Models;

use App\Domain\Inventory\Models\StockLocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** A physical register. OSPOS had no equivalent, so cash could not be attributed to a drawer. */
class Terminal extends Model
{
    use SoftDeletes;

    public const CONNECTOR_NETWORK = 'network';

    public const CONNECTOR_WINDOWS = 'windows';

    public const CONNECTOR_CUPS = 'cups';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'printer_paper_width' => 'integer',
        ];
    }

    public function hasPrinterConfigured(): bool
    {
        return $this->printer_connector !== null && $this->receipt_printer !== null;
    }

    public function stockLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function openShift(): ?Shift
    {
        return $this->shifts()->where('status', Shift::STATUS_OPEN)->latest('opened_at')->first();
    }
}
