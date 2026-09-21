<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Actions;

use App\Domain\Loyalty\Models\PointsTransaction;
use App\Domain\Sales\Models\Sale;

/**
 * Runs unconditionally at the end of every completed sale -- a no-op when
 * the sale has no customer, or the customer's package earns nothing, rather
 * than something CompleteSaleAction has to decide whether to call.
 */
final class AwardLoyaltyPointsAction
{
    public function execute(Sale $sale): void
    {
        $customer = $sale->customer;

        if ($customer === null) {
            return;
        }

        $package = $customer->loyaltyPackage;

        if ($package === null || ! $package->is_active || bccomp((string) $package->points_per_currency_unit, '0', 4) <= 0) {
            return;
        }

        // Net product spend: excludes tax (not revenue) and discounted
        // amounts (no reward for a discount already given). $sale->subtotal
        // is already net of every line discount (see CartPricer::price()),
        // so it must not be discounted a second time here.
        $netSpend = $sale->subtotal;
        $points = bcmul((string) $netSpend->getAmount(), (string) $package->points_per_currency_unit, 3);

        if (bccomp($points, '0', 3) <= 0) {
            return;
        }

        $balanceAfter = bcadd((string) $customer->points_balance, $points, 3);

        PointsTransaction::create([
            'customer_id' => $customer->id,
            'sale_id' => $sale->id,
            'loyalty_package_id' => $package->id,
            'type' => 'earn',
            'points' => $points,
            'balance_after' => $balanceAfter,
            'expires_at' => $package->points_expire_after_days !== null
                ? now()->addDays($package->points_expire_after_days)
                : null,
            'remaining_points' => $points,
        ]);

        $customer->update(['points_balance' => $balanceAfter]);
    }
}
