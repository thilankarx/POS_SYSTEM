<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Actions;

use App\Domain\Crm\Models\Customer;
use App\Domain\Loyalty\Models\PointsTransaction;

/**
 * Draws $amount down against a customer's points-adding ledger rows
 * (earn, or a positive adjustment), oldest-earned-first, so a later
 * expiry check only ever has to expire what's actually left unconsumed
 * on a batch. Pure ledger bookkeeping: doesn't create a transaction row
 * or touch Customer.points_balance -- the caller (redemption or a
 * negative manual adjustment) already does both, under the same
 * customer row lock that already serializes concurrent access here.
 */
final class ConsumeEarnBatchesAction
{
    public function execute(Customer $customer, string $amount): void
    {
        $remaining = $amount;

        $batches = PointsTransaction::query()
            ->where('customer_id', $customer->id)
            ->where('remaining_points', '>', 0)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        foreach ($batches as $batch) {
            if (bccomp($remaining, '0', 3) <= 0) {
                break;
            }

            $batchRemaining = (string) $batch->remaining_points;
            $consumed = bccomp($batchRemaining, $remaining, 3) < 0 ? $batchRemaining : $remaining;

            $batch->update(['remaining_points' => bcsub($batchRemaining, $consumed, 3)]);
            $remaining = bcsub($remaining, $consumed, 3);
        }
    }
}
