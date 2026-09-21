<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\InventoryService;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Reporting\Queries\InventoryReportQuery;

beforeEach(function () {
    $this->seed();

    $this->user = User::where('username', 'cashier')->firstOrFail();
    $this->main = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->warehouse = StockLocation::where('code', 'WH')->firstOrFail();
    $this->cola = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    $this->bread = Item::where('sku', 'BAK-BREAD-WHT')->firstOrFail();
});

// The demo seeder itself writes an opening-stock receiving movement per item
// through the ledger, so a fresh test DB is not empty — every assertion below
// is a delta against a captured baseline rather than an absolute count.

it('filters movements by date range, item, location and reason', function () {
    $query = app(InventoryReportQuery::class);
    $from = now()->subDay();
    $to = now()->addDay();

    $baselineTotal = $query->movements($from, $to)->total();
    $baselineCola = $query->movements($from, $to, itemId: $this->cola->id)->total();
    $baselineWarehouse = $query->movements($from, $to, stockLocationId: $this->warehouse->id)->total();
    $baselineSale = $query->movements($from, $to, reason: StockMovement::REASON_SALE)->total();

    $inventory = app(InventoryService::class);
    $inventory->record($this->cola, $this->main->id, '10', StockMovement::REASON_RECEIVING, userId: $this->user->id);
    $inventory->record($this->cola, $this->main->id, '-3', StockMovement::REASON_SALE, userId: $this->user->id);
    $inventory->record($this->bread, $this->warehouse->id, '5', StockMovement::REASON_RECEIVING, userId: $this->user->id);

    expect($query->movements($from, $to)->total())->toBe($baselineTotal + 3)
        ->and($query->movements($from, $to, itemId: $this->cola->id)->total())->toBe($baselineCola + 2)
        ->and($query->movements($from, $to, stockLocationId: $this->warehouse->id)->total())->toBe($baselineWarehouse + 1)
        ->and($query->movements($from, $to, reason: StockMovement::REASON_SALE)->total())->toBe($baselineSale + 1)
        ->and($query->movements(now()->addDays(5), now()->addDays(10))->total())->toBe(0);
});

it('summarizes quantity in and out by reason', function () {
    $before = app(InventoryReportQuery::class)->summaryByReason(now()->subDay(), now()->addDay())->keyBy('reason');
    $beforeSaleOut = isset($before[StockMovement::REASON_SALE]) ? (string) $before[StockMovement::REASON_SALE]->quantity_out : '0.000';
    $beforeSaleCount = isset($before[StockMovement::REASON_SALE]) ? (int) $before[StockMovement::REASON_SALE]->movement_count : 0;

    $inventory = app(InventoryService::class);
    $inventory->record($this->cola, $this->main->id, '10', StockMovement::REASON_RECEIVING, userId: $this->user->id);
    $inventory->record($this->cola, $this->main->id, '-3', StockMovement::REASON_SALE, userId: $this->user->id);
    $inventory->record($this->cola, $this->main->id, '-2', StockMovement::REASON_SALE, userId: $this->user->id);

    $rows = app(InventoryReportQuery::class)->summaryByReason(now()->subDay(), now()->addDay())->keyBy('reason');

    expect(bcsub((string) $rows[StockMovement::REASON_SALE]->quantity_out, $beforeSaleOut, 3))->toBe('-5.000')
        ->and((int) $rows[StockMovement::REASON_SALE]->movement_count)->toBe($beforeSaleCount + 2);
});

it('exports movements as a lazy collection with item and location loaded', function () {
    $before = app(InventoryReportQuery::class)->movementsForExport(now()->subDay(), now()->addDay())->count();

    $inventory = app(InventoryService::class);
    $inventory->record($this->cola, $this->main->id, '10', StockMovement::REASON_RECEIVING, userId: $this->user->id);

    $rows = app(InventoryReportQuery::class)->movementsForExport(now()->subDay(), now()->addDay())->all();
    $newest = end($rows);

    expect($rows)->toHaveCount($before + 1)
        ->and($newest->item->sku)->toBe('BEV-COLA-330')
        ->and($newest->stockLocation->code)->toBe('MAIN');
});
