<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Crm\Models\Customer;
use App\Domain\Loyalty\Models\PointsTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Expires the unconsumed remainder of each earn batch past its package's
 * expiry window. Only ever expires a batch's current remaining_points,
 * not its original earned amount, so a partially-redeemed batch loses
 * only what's left of it.
 */
class ExpireLoyaltyPointsCommand extends Command
{
    protected $signature = 'loyalty:expire-points';

    protected $description = "Expire loyalty points whose earn-batch has passed its package's expiry window";

    public function handle(): int
    {
        $customerIds = PointsTransaction::query()
            ->where('type', 'earn')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->where('remaining_points', '>', 0)
            ->distinct()
            ->pluck('customer_id');

        $expired = [];

        foreach ($customerIds as $customerId) {
            DB::transaction(function () use ($customerId, &$expired) {
                $customer = Customer::query()->lockForUpdate()->find($customerId);

                if ($customer === null) {
                    return;
                }

                $dueBatches = PointsTransaction::query()
                    ->where('customer_id', $customer->id)
                    ->where('type', 'earn')
                    ->whereNotNull('expires_at')
                    ->where('expires_at', '<=', now())
                    ->where('remaining_points', '>', 0)
                    ->orderBy('created_at')
                    ->orderBy('id')
                    ->get();

                foreach ($dueBatches as $batch) {
                    $amount = (string) $batch->remaining_points;

                    if (bccomp($amount, '0', 3) <= 0) {
                        continue;
                    }

                    $balanceAfter = bcsub((string) $customer->points_balance, $amount, 3);

                    PointsTransaction::create([
                        'customer_id' => $customer->id,
                        'loyalty_package_id' => $batch->loyalty_package_id,
                        'type' => 'expire',
                        'points' => bcmul($amount, '-1', 3),
                        'balance_after' => $balanceAfter,
                    ]);

                    $batch->update(['remaining_points' => '0']);
                    $customer->update(['points_balance' => $balanceAfter]);

                    $expired[] = [$customer->id, $amount, $batch->id];
                }
            });
        }

        if ($expired === []) {
            $this->info('No expired points.');

            return self::SUCCESS;
        }

        $this->table(['Customer', 'Points expired', 'Batch #'], $expired);
        $this->info(sprintf('Expired points for %d batch(es).', count($expired)));

        return self::SUCCESS;
    }
}
