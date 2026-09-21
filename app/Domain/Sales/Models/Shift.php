<?php

declare(strict_types=1);

namespace App\Domain\Sales\Models;

use App\Domain\Identity\Models\User;
use App\Support\Money\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    // Open/close activity logging lives in App\Listeners\LogShiftOpened /
    // LogShiftClosed (fired off ShiftOpened/ShiftClosed), not here -- adding
    // LogsActivity's automatic dirty-tracking on top would double-log every
    // open and close.
    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'opening_float' => MoneyCast::class,
            'expected_cash' => MoneyCast::class,
            'counted_cash' => MoneyCast::class,
            'cash_variance' => MoneyCast::class,
            'expected_non_cash' => MoneyCast::class,
            'counted_non_cash' => MoneyCast::class,
            'cash_dropped' => MoneyCast::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(Terminal::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function cashCounts(): HasMany
    {
        return $this->hasMany(ShiftCashCount::class);
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
