<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Crm\Models\Supplier;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\InventoryService;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Purchasing\Queries\ReorderSuggestionsQuery;
use App\Domain\Sales\Events\SaleCompleted;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Models\SaleLine;
use App\Listeners\LogLowStockOnSaleCompleted;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed();
    $this->owner = User::where('username', 'admin')->firstOrFail();
    $this->supplier = Supplier::firstOrFail();
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
});

function lowStockItem(array $overrides = []): Item
{
    static $n = 0;
    $n++;

    return Item::create(array_merge([
        'sku' => "LISTENER-TEST-{$n}",
        'name' => "Listener Test Item {$n}",
        'supplier_id' => test()->supplier->id,
        'reorder_level' => 10,
        'stock_type' => Item::STOCK_TYPE_STOCKED,
        'is_active' => true,
    ], $overrides));
}

function saleFor(StockLocation $location, User $user, array $items): Sale
{
    $sale = Sale::create([
        'number' => 'S-LISTENER-'.uniqid(),
        'user_id' => $user->id,
        'stock_location_id' => $location->id,
    ]);

    foreach ($items as $index => [$item, $quantity]) {
        SaleLine::create([
            'sale_id' => $sale->id,
            'line_number' => $index + 1,
            'item_id' => $item->id,
            'stock_location_id' => $location->id,
            'item_name' => $item->name,
            'sku' => $item->sku,
            'quantity' => $quantity,
            'unit_price' => '9.99',
        ]);
    }

    return $sale->refresh();
}

it('logs a low-stock activity for an item that dropped below its reorder level', function () {
    $item = lowStockItem(['reorder_level' => '10']);
    app(InventoryService::class)->record($item, $this->location->id, '5', StockMovement::REASON_ADJUSTMENT);

    $sale = saleFor($this->location, $this->owner, [[$item, '1']]);

    (new LogLowStockOnSaleCompleted(app(ReorderSuggestionsQuery::class)))
        ->handle(new SaleCompleted($sale));

    $activity = Activity::where('log_name', 'inventory')->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->subject_type)->toBe(Item::class)
        ->and($activity->subject_id)->toBe($item->id)
        ->and($activity->description)->toContain($item->name)
        ->and($activity->properties->get('triggered_by_sale_id'))->toBe($sale->id);
});

it('does not log anything for an item still above its reorder level', function () {
    $item = lowStockItem(['reorder_level' => '10']);
    app(InventoryService::class)->record($item, $this->location->id, '50', StockMovement::REASON_ADJUSTMENT);

    $sale = saleFor($this->location, $this->owner, [[$item, '1']]);

    (new LogLowStockOnSaleCompleted(app(ReorderSuggestionsQuery::class)))
        ->handle(new SaleCompleted($sale));

    expect(Activity::where('log_name', 'inventory')->count())->toBe(0);
});
