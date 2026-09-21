<?php

declare(strict_types=1);

namespace App\Domain\Loyalty\Actions;

use App\Domain\Crm\Models\Customer;
use App\Domain\Identity\Models\User;
use App\Domain\Loyalty\Exceptions\LoyaltyException;
use App\Domain\Loyalty\Models\PointsTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Manual back-office correction/goodwill award, outside a sale. $points is
 * signed: positive to award, negative to deduct.
 */
final class AdjustPointsAction
{
    public function __construct(
        private readonly ConsumeEarnBatchesAction $consumeEarnBatches,
    ) {}

    public function execute(Customer $customer, string $points, User $user): PointsTransaction
    {
        return DB::transaction(function () use ($customer, $points, $user) {
            $locked = Customer::query()->lockForUpdate()->findOrFail($customer->id);
            $balanceAfter = bcadd((string) $locked->points_balance, $points, 3);

            if (bccomp($balanceAfter, '0', 3) < 0) {
                throw LoyaltyException::insufficientPoints();
            }

            $isAward = bccomp($points, '0', 3) > 0;

            $transaction = PointsTransaction::create([
                'customer_id' => $locked->id,
                'loyalty_package_id' => $locked->loyalty_package_id,
                'user_id' => $user->id,
                'type' => 'adjustment',
                'points' => $points,
                'balance_after' => $balanceAfter,
                'remaining_points' => $isAward ? $points : null,
            ]);

            $locked->update(['points_balance' => $balanceAfter]);

            if (! $isAward) {
                $this->consumeEarnBatches->execute($locked, ltrim($points, '-'));
            }

            return $transaction;
        });
    }
}
