<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\InventoryService;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Models\StockMovement;

beforeEach(function () {
    $this->seed();
    $this->service = app(InventoryService::class);
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->user = User::where('username', 'admin')->firstOrFail();
    $this->item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
});

it('keeps the projection equal to the replayed ledger', function () {
    foreach (['12', '-5', '30', '-2.5', '-0.5'] as $delta) {
        $this->service->record(
            item: $this->item,
            stockLocationId: $this->location->id,
            quantityDelta: $delta,
            reason: StockMovement::REASON_ADJUSTMENT,
            userId: $this->user->id,
        );
    }

    $result = $this->service->reconcile($this->item->id, $this->location->id);

    expect($result['drift'])->toBe('0.000')
        ->and($result['projection'])->toBe($result['ledger']);
});

it('records the running balance on each movement', function () {
    $opening = (string) $this->item->stockLevels()
        ->where('stock_location_id', $this->location->id)->value('quantity');

    $movement = $this->service->record(
        item: $this->item,
        stockLocationId: $this->location->id,
        quantityDelta: '-10',
        reason: StockMovement::REASON_ADJUSTMENT,
        userId: $this->user->id,
    );

    expect((string) $movement->balance_after)->toBe(bcsub($opening, '10', 3));
});

it('writes no movement for a service item', function () {
    $service = Item::where('sku', 'SRV-DELIVERY')->firstOrFail();

    $movement = $this->service->record(
        item: $service,
        stockLocationId: $this->location->id,
        quantityDelta: '5',
        reason: StockMovement::REASON_ADJUSTMENT,
        userId: $this->user->id,
    );

    expect($movement)->toBeNull()
        ->and(StockMovement::where('item_id', $service->id)->count())->toBe(0);
});

it('ignores a zero-quantity movement', function () {
    $before = StockMovement::count();

    $this->service->record(
        item: $this->item,
        stockLocationId: $this->location->id,
        quantityDelta: '0',
        reason: StockMovement::REASON_ADJUSTMENT,
    );

    expect(StockMovement::count())->toBe($before);
});

it('refuses a non-numeric quantity rather than building unsafe SQL', function () {
    $this->service->record(
        item: $this->item,
        stockLocationId: $this->location->id,
        quantityDelta: '1; DROP TABLE stock_levels',
        reason: StockMovement::REASON_ADJUSTMENT,
    );
})->throws(InvalidArgumentException::class);
