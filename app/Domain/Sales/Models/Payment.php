<?php

declare(strict_types=1);

namespace App\Domain\Sales\Models;

use App\Domain\Identity\Models\User;
use App\Support\Money\MoneyCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A payment, with a real gateway lifecycle.
 *
 * OSPOS stored a translated label and an amount: no authorisation, no capture,
 * no void, no provider reference, and therefore no way to reconcile the till
 * against what the processor actually settled.
 */
class Payment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_AUTHORIZED = 'authorized';

    public const STATUS_CAPTURED = 'captured';

    public const STATUS_FAILED = 'failed';

    public const STATUS_VOIDED = 'voided';

    public const STATUS_REFUNDED = 'refunded';

    protected $guarded = ['id'];

    protected $hidden = ['provider_payload'];

    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class,
            'tendered' => MoneyCast::class,
            'change_given' => MoneyCast::class,
            'provider_payload' => 'encrypted:array',
            'captured_at' => 'datetime',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function refundsPayment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'refunds_payment_id');
    }

    public function isSettled(): bool
    {
        return $this->status === self::STATUS_CAPTURED;
    }
}
