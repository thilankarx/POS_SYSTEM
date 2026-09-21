<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Actions;

use App\Domain\Crm\Models\Customer;
use App\Domain\Loyalty\Exceptions\LoyaltyException;
use App\Domain\Loyalty\Models\PointsTransaction;
use App\Domain\Sales\Models\Cart;
use App\Domain\Sales\Models\Sale;

/**
 * Redemption limits are only advisory at AddCartPaymentAction time -- the
 * authoritative, race-safe check happens here, under a row lock, inside
 * CompleteSaleAction's transaction. Mirrors
 * RecordPromotionRedemptionsAction's verifyAndLock()/commit() shape.
 */
final class RecordPointsRedemptionAction
{
    public function __construct(
        private readonly ConsumeEarnBatchesAction $consumeEarnBatches,
    ) {}

    public function verifyAndLock(Cart $cart): ?Customer
    {
        $pointsPayment = $cart->payments->first(fn ($payment) => $payment->method?->code === 'points');

        if ($pointsPayment === null) {
            return null;
        }

        if ($cart->customer_id === null) {
            throw LoyaltyException::noLoyaltyPackage();
        }

        $customer = Customer::query()->lockForUpdate()->findOrFail($cart->customer_id);
        $package = $customer->loyaltyPackage;

        if ($package === null || bccomp((string) $package->currency_value_per_point, '0', 4) <= 0) {
            throw LoyaltyException::noLoyaltyPackage();
        }

        $pointsNeeded = bcdiv((string) $pointsPayment->amount->getAmount(), (string) $package->currency_value_per_point, 3);

        if (bccomp((string) $customer->points_balance, $pointsNeeded, 3) < 0) {
            throw LoyaltyException::insufficientPoints();
        }

        return $customer;
    }

    public function commit(Sale $sale, Cart $cart, ?Customer $lockedCustomer): void
    {
        if ($lockedCustomer === null) {
            return;
        }

        $pointsPayment = $cart->payments->first(fn ($payment) => $payment->method?->code === 'points');
        $package = $lockedCustomer->loyaltyPackage;
        $pointsSpent = bcdiv((string) $pointsPayment->amount->getAmount(), (string) $package->currency_value_per_point, 3);
        $balanceAfter = bcsub((string) $lockedCustomer->points_balance, $pointsSpent, 3);

        PointsTransaction::create([
            'customer_id' => $lockedCustomer->id,
            'sale_id' => $sale->id,
            'loyalty_package_id' => $package->id,
            'type' => 'redeem',
            'points' => bcmul($pointsSpent, '-1', 3),
            'balance_after' => $balanceAfter,
        ]);

        $lockedCustomer->update(['points_balance' => $balanceAfter]);

        $this->consumeEarnBatches->execute($lockedCustomer, $pointsSpent);
    }
}
