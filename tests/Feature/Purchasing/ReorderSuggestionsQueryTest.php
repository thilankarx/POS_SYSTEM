<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Crm\Models\Supplier;
use App\Domain\Inventory\InventoryService;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Purchasing\Queries\ReorderSuggestionsQuery;

beforeEach(function () {
    $this->seed();
    $this->supplier = Supplier::firstOrFail();
    $this->main = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->warehouse = StockLocation::where('code', 'WH')->firstOrFail();
    $this->query = app(ReorderSuggestionsQuery::class);
});

function reorderItem(array $overrides = []): Item
{
    static $n = 0;
    $n++;

    return Item::create(array_merge([
        'sku' => "REORDER-TEST-{$n}",
        'name' => "Reorder Test Item {$n}",
        'supplier_id' => test()->supplier->id,
        'reorder_level' => 10,
        'stock_type' => Item::STOCK_TYPE_STOCKED,
        'is_active' => true,
    ], $overrides));
}

function setOnHand(Item $item, StockLocation $location, string $quantity): void
{
    app(InventoryService::class)->record($item, $location->id, $quantity, StockMovement::REASON_ADJUSTMENT);
}

it('suggests an item whose on-hand is below its reorder level', function () {
    $item = reorderItem(['reorder_level' => '10']);
    setOnHand($item, $this->main, '3');

    $suggestions = $this->query->forLocation($this->main);

    expect($suggestions->pluck('id'))->toContain($item->id);
    expect((string) $suggestions->firstWhere('id', $item->id)->on_hand)->toBe('3.000');
});

it('excludes an item that is fully stocked', function () {
    $item = reorderItem(['reorder_level' => '10']);
    setOnHand($item, $this->main, '50');

    expect($this->query->forLocation($this->main)->pluck('id'))->not->toContain($item->id);
});

it('excludes an item with reorder_level of 0', function () {
    $item = reorderItem(['reorder_level' => '0']);
    setOnHand($item, $this->main, '0');

    expect($this->query->forLocation($this->main)->pluck('id'))->not->toContain($item->id);
});

it('excludes a service item even when below its reorder level', function () {
    $item = reorderItem(['reorder_level' => '10', 'stock_type' => Item::STOCK_TYPE_SERVICE]);

    expect($this->query->forLocation($this->main)->pluck('id'))->not->toContain($item->id);
});

it('excludes an item with no supplier', function () {
    $item = reorderItem(['reorder_level' => '10', 'supplier_id' => null]);
    setOnHand($item, $this->main, '1');

    expect($this->query->forLocation($this->main)->pluck('id'))->not->toContain($item->id);
});

it('excludes an inactive item', function () {
    $item = reorderItem(['reorder_level' => '10', 'is_active' => false]);
    setOnHand($item, $this->main, '1');

    expect($this->query->forLocation($this->main)->pluck('id'))->not->toContain($item->id);
});

it('scopes on-hand to the queried location', function () {
    $item = reorderItem(['reorder_level' => '10']);
    setOnHand($item, $this->main, '1');
    setOnHand($item, $this->warehouse, '50');

    expect($this->query->forLocation($this->main)->pluck('id'))->toContain($item->id);
    expect($this->query->forLocation($this->warehouse)->pluck('id'))->not->toContain($item->id);
});

it('suggests reorder_quantity when set, falling back to topping up to reorder_level', function () {
    $withQuantity = reorderItem(['reorder_level' => '10', 'reorder_quantity' => '24']);
    setOnHand($withQuantity, $this->main, '3');

    $withoutQuantity = reorderItem(['reorder_level' => '10']);
    setOnHand($withoutQuantity, $this->main, '3');

    expect($this->query->suggestedQuantity($withQuantity->fresh()))->toBe('24.000')
        ->and($this->query->suggestedQuantity($this->query->forLocation($this->main)->firstWhere('id', $withoutQuantity->id)))->toBe('7.000');
});
