<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Inventory\InventoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Replays the stock ledger and reports any drift against the projection.
 *
 * OSPOS shipped `Inventory::reset_quantity()` to silently overwrite the
 * quantity when its two structures disagreed. This reports the disagreement
 * instead, and only repairs it when explicitly asked.
 */
class ReconcileStockCommand extends Command
{
    protected $signature = 'stock:reconcile {--fix : Rewrite the projection from the ledger where they differ}';

    protected $description = 'Compare stock levels against a replay of the movement ledger';

    public function handle(InventoryService $inventory): int
    {
        $pairs = DB::table('stock_levels')
            ->select('item_id', 'stock_location_id')
            ->union(
                DB::table('stock_movements')->select('item_id', 'stock_location_id')->distinct()
            )
            ->get();

        $drifted = [];

        foreach ($pairs as $pair) {
            $result = $inventory->reconcile((int) $pair->item_id, (int) $pair->stock_location_id);

            if (bccomp($result['drift'], '0', 3) === 0) {
                continue;
            }

            $drifted[] = [
                $pair->item_id,
                $pair->stock_location_id,
                $result['ledger'],
                $result['projection'],
                $result['drift'],
            ];

            if ($this->option('fix')) {
                DB::table('stock_levels')
                    ->where('item_id', $pair->item_id)
                    ->where('stock_location_id', $pair->stock_location_id)
                    ->update(['quantity' => $result['ledger'], 'updated_at' => now()]);
            }
        }

        if ($drifted === []) {
            $this->info(sprintf('Reconciled %d item/location pairs. No drift.', $pairs->count()));

            return self::SUCCESS;
        }

        $this->table(['Item', 'Location', 'Ledger', 'Projection', 'Drift'], $drifted);

        if ($this->option('fix')) {
            $this->warn(sprintf('Repaired %d drifted pair(s) from the ledger.', count($drifted)));

            return self::SUCCESS;
        }

        $this->error(sprintf('%d pair(s) drifted. Re-run with --fix to repair from the ledger.', count($drifted)));

        return self::FAILURE;
    }
}
