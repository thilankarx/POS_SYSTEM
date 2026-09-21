<?php

declare(strict_types=1);

namespace App\Domain\Sales\Events;

use App\Domain\Sales\Models\Sale;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once a sale is durably committed.
 *
 * Everything that is not part of taking the money hangs off this: loyalty
 * points, gift-card balances, receipt email, Mailchimp sync, low-stock checks.
 * OSPOS did all of it inline inside a 180-line save method, which is why a
 * failing Mailchimp call could take a sale down with it.
 */
final class SaleCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public readonly Sale $sale) {}
}
