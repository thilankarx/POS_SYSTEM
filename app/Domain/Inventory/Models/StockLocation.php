<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockLocation extends Model
{
    use SoftDeletes;

    public const CONNECTOR_NETWORK = 'network';

    public const CONNECTOR_WINDOWS = 'windows';

    public const CONNECTOR_CUPS = 'cups';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'sells' => 'boolean',
            'receives' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function hasLabelPrinterConfigured(): bool
    {
        return $this->label_printer_connector !== null && $this->label_printer !== null;
    }

    public function hasKitchenPrinterConfigured(): bool
    {
        return $this->kitchen_printer_connector !== null && $this->kitchen_printer !== null;
    }
}
