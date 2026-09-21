<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * remaining_points tracks how much of a points-adding row (earn, or a
 * positive adjustment) has not yet been drawn down by a later
 * redemption/negative-adjustment/expiry -- null on points-subtracting
 * rows, which are debits rather than a batch. The backfill below replays
 * each customer's existing ledger in order so the invariant
 * sum(remaining_points) == points_balance holds immediately, instead of
 * leaving pre-existing rows at null (which would read as already fully
 * consumed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('points_transactions', function (Blueprint $table) {
            $table->decimal('remaining_points', 15, 3)->nullable()->after('expires_at');
        });

        $rows = DB::table('points_transactions')
            ->orderBy('customer_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'customer_id', 'type', 'points']);

        foreach ($rows->groupBy('customer_id') as $customerRows) {
            /** @var array<int, array{id: int, remaining: string}> $queue */
            $queue = [];
            $updates = [];

            foreach ($customerRows as $row) {
                $points = (string) $row->points;
                $adds = $row->type === 'earn' || ($row->type === 'adjustment' && bccomp($points, '0', 3) > 0);

                if ($adds) {
                    $queue[] = ['id' => $row->id, 'remaining' => $points];

                    continue;
                }

                $toConsume = ltrim($points, '-');

                while (bccomp($toConsume, '0', 3) > 0 && $queue !== []) {
                    $batch = &$queue[0];
                    $consumed = bccomp($batch['remaining'], $toConsume, 3) < 0 ? $batch['remaining'] : $toConsume;
                    $batch['remaining'] = bcsub($batch['remaining'], $consumed, 3);
                    $toConsume = bcsub($toConsume, $consumed, 3);
                    unset($batch);

                    if (bccomp($queue[0]['remaining'], '0', 3) === 0) {
                        $updates[$queue[0]['id']] = '0.000';
                        array_shift($queue);
                    }
                }
            }

            foreach ($queue as $batch) {
                $updates[$batch['id']] = $batch['remaining'];
            }

            foreach ($updates as $id => $remaining) {
                DB::table('points_transactions')->where('id', $id)->update(['remaining_points' => $remaining]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('points_transactions', function (Blueprint $table) {
            $table->dropColumn('remaining_points');
        });
    }
};
