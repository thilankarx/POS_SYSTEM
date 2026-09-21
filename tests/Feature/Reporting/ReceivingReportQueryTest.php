<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Crm\Models\Supplier;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Purchasing\Actions\ReceiveGoodsAction;
use App\Domain\Purchasing\Models\Receiving;
use App\Domain\Reporting\Queries\ReceivingReportQuery;

beforeEach(function () {
    $this->seed();
    $this->admin = User::where('username', 'admin')->firstOrFail();
    $this->supplier = Supplier::firstOrFail();
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
});

function receiveForReceivingsReport(string $quantity, string $unitCost, string $type = Receiving::TYPE_RECEIPT): Receiving
{
    return app(ReceiveGoodsAction::class)->execute(
        purchaseOrder: null,
        supplier: test()->supplier,
        location: test()->location,
        user: test()->admin,
        type: $type,
        lines: [['item_id' => test()->item->id, 'quantity' => $quantity, 'unit_cost' => $unitCost, 'lot_number' => 'LOT-RPT']],
    );
}

it('paginates receivings filtered by date range, supplier, location and type', function () {
    $receiving = receiveForReceivingsReport('10', '0.50');

    $query = app(ReceivingReportQuery::class);

    expect($query->receivings(now()->subDay(), now()->addDay())->total())->toBe(1)
        ->and($query->receivings(now()->subDay(), now()->addDay(), supplierId: $this->supplier->id)->total())->toBe(1)
        ->and($query->receivings(now()->subDay(), now()->addDay(), stockLocationId: $this->location->id)->total())->toBe(1)
        ->and($query->receivings(now()->subDay(), now()->addDay(), type: Receiving::TYPE_RECEIPT)->total())->toBe(1)
        ->and($query->receivings(now()->subDay(), now()->addDay(), type: Receiving::TYPE_RETURN_TO_SUPPLIER)->total())->toBe(0)
        ->and($query->receivings(now()->addDays(5), now()->addDays(10))->total())->toBe(0);

    expect($query->receivings(now()->subDay(), now()->addDay())->first()->id)->toBe($receiving->id);
});

it('summarizes total by type', function () {
    // BEV-COLA-330 carries the "standard" 15% tax category, so totals are
    // tax-inclusive: 10 x 0.50 = 5.00 -> 5.75; 2 x 0.50 = 1.00 -> 1.15.
    receiveForReceivingsReport('10', '0.50');
    receiveForReceivingsReport('2', '0.50', Receiving::TYPE_RETURN_TO_SUPPLIER);

    $rows = app(ReceivingReportQuery::class)->summaryByType(now()->subDay(), now()->addDay())->keyBy('type');

    expect((string) $rows[Receiving::TYPE_RECEIPT]->total->getAmount())->toBe('5.75')
        ->and((string) $rows[Receiving::TYPE_RETURN_TO_SUPPLIER]->total->getAmount())->toBe('1.15');
});

it('exports receivings as a lazy collection with supplier and location loaded', function () {
    $receiving = receiveForReceivingsReport('10', '0.50');

    $rows = app(ReceivingReportQuery::class)->receivingsForExport(now()->subDay(), now()->addDay())->all();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->id)->toBe($receiving->id)
        ->and($rows[0]->supplier->id)->toBe($this->supplier->id)
        ->and($rows[0]->stockLocation->code)->toBe('MAIN');
});
