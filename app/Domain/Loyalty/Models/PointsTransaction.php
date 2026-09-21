<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Models;

use App\Domain\Crm\Models\Customer;
use App\Domain\Identity\Models\User;
use App\Domain\Sales\Models\Sale;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Points as a ledger, not a mutable balance column. */
class PointsTransaction extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'points' => 'decimal:3',
            'balance_after' => 'decimal:3',
            'expires_at' => 'datetime',
            'remaining_points' => 'decimal:3',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
