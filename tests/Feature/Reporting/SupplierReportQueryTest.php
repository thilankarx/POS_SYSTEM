<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Crm\Models\Supplier;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Purchasing\Actions\ReceiveGoodsAction;
use App\Domain\Purchasing\Models\Receiving;
use App\Domain\Reporting\Queries\SupplierReportQuery;

beforeEach(function () {
    $this->seed();
    $this->admin = User::where('username', 'admin')->firstOrFail();
    $this->supplier = Supplier::firstOrFail();
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
});

function receiveForSupplierReport(string $quantity, string $unitCost, string $type = Receiving::TYPE_RECEIPT, string $lotNumber = 'LOT-RPT'): Receiving
{
    return app(ReceiveGoodsAction::class)->execute(
        purchaseOrder: null,
        supplier: test()->supplier,
        location: test()->location,
        user: test()->admin,
        type: $type,
        lines: [['item_id' => test()->item->id, 'quantity' => $quantity, 'unit_cost' => $unitCost, 'lot_number' => $lotNumber]],
    );
}

it('aggregates receiving count and total spend per supplier', function () {
    // BEV-COLA-330 carries the "standard" 15% tax category, so each receiving's
    // total is tax-inclusive: 10 x 0.50 = 5.00 -> 5.75; 4 x 0.50 = 2.00 -> 2.30.
    // Each receipt is a distinct physical batch, so it needs its own lot number
    // -- ReceiveGoodsAction refuses to receive stock twice into the same lot.
    receiveForSupplierReport('10', '0.50', lotNumber: 'LOT-RPT-1');
    receiveForSupplierReport('4', '0.50', lotNumber: 'LOT-RPT-2');

    $rows = app(SupplierReportQuery::class)->bySupplier(now()->subDay(), now()->addDay())->keyBy('supplier_id');
    $row = $rows[test()->supplier->id];

    expect((int) $row->receiving_count)->toBe(2)
        ->and((string) $row->total->getAmount())->toBe('8.05')
        ->and((float) $row->avg_receiving)->toBe(4.025);
});

it('excludes non-receipt receivings (returns/transfers)', function () {
    receiveForSupplierReport('5', '0.50', Receiving::TYPE_RETURN_TO_SUPPLIER);

    $rows = app(SupplierReportQuery::class)->bySupplier(now()->subDay(), now()->addDay());

    expect($rows)->toHaveCount(0);
});

it('excludes receivings outside the date range', function () {
    receiveForSupplierReport('5', '0.50');

    $rows = app(SupplierReportQuery::class)->bySupplier(now()->addDays(5), now()->addDays(10));

    expect($rows)->toHaveCount(0);
});
