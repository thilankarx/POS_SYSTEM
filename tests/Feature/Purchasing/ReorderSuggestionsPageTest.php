<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Crm\Models\Supplier;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\InventoryService;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Models\StockLot;
use App\Domain\Inventory\Models\StockMovement;
use App\Livewire\Purchasing\ReorderSuggestions\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->admin = User::where('username', 'admin')->firstOrFail();
    $this->supplier = Supplier::firstOrFail();
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();

    $this->outOfStockItem = Item::create([
        'sku' => 'REORDER-PAGE-OOS',
        'name' => 'Out of Stock Test Item',
        'supplier_id' => $this->supplier->id,
        'reorder_level' => '10',
        'reorder_quantity' => '24',
        'stock_type' => Item::STOCK_TYPE_STOCKED,
        'is_active' => true,
    ]);
    StockLot::create([
        'item_id' => $this->outOfStockItem->id,
        'lot_number' => 'LOT-REORDER-PAGE',
        'cost_price' => '5.00',
    ]);

    $this->lowStockItem = Item::create([
        'sku' => 'REORDER-PAGE-LOW',
        'name' => 'Low Stock Test Item',
        'supplier_id' => $this->supplier->id,
        'reorder_level' => '10',
        'reorder_quantity' => '12',
        'stock_type' => Item::STOCK_TYPE_STOCKED,
        'is_active' => true,
    ]);
    app(InventoryService::class)->record(
        $this->lowStockItem,
        $this->location->id,
        '3',
        StockMovement::REASON_ADJUSTMENT,
    );
});

it('shows urgency, projected stock, and estimated reorder values', function () {
    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('stock_location_id', $this->location->id)
        ->assertSee($this->outOfStockItem->name)
        ->assertSee($this->lowStockItem->name)
        ->assertSee('Out of stock')
        ->assertSee('Low stock')
        ->assertSee('Projected stock')
        ->assertSee('120.00')
        ->set('urgency', 'out_of_stock')
        ->assertSee($this->outOfStockItem->name)
        ->assertDontSee($this->lowStockItem->name)
        ->call('clearFilters')
        ->set('search', 'REORDER-PAGE-LOW')
        ->assertSee($this->lowStockItem->name)
        ->assertDontSee($this->outOfStockItem->name);
});

it('supports supplier selection controls and validates selected costs', function () {
    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('stock_location_id', $this->location->id)
        ->call('selectSupplier', $this->supplier->id, false)
        ->assertSet("selected.{$this->outOfStockItem->id}", false)
        ->assertSet("selected.{$this->lowStockItem->id}", false)
        ->call('selectSupplier', $this->supplier->id, true)
        ->set("unitCosts.{$this->outOfStockItem->id}", '1.234')
        ->call('createPurchaseOrder', $this->supplier->id)
        ->assertHasErrors("unitCosts.{$this->outOfStockItem->id}");
});
