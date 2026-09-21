<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Crm\Models\Supplier;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\InventoryService;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Livewire\Purchasing\ReorderSuggestions\Index as ReorderSuggestionsIndex;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->admin = User::where('username', 'admin')->firstOrFail();
    $this->stockClerk = User::factory()->create();
    $this->stockClerk->assignRole('Stock Clerk');
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
    $this->supplier = Supplier::firstOrFail();
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();

    $this->item = Item::create([
        'sku' => 'REORDER-BO-1',
        'name' => 'Reorder Backoffice Item',
        'supplier_id' => $this->supplier->id,
        'reorder_level' => '10',
        'reorder_quantity' => '24',
        'stock_type' => Item::STOCK_TYPE_STOCKED,
        'is_active' => true,
    ]);
    app(InventoryService::class)->record($this->item, $this->location->id, '3', StockMovement::REASON_ADJUSTMENT);
});

it('forbids a Cashier from viewing reorder suggestions', function () {
    $this->actingAs($this->cashier)->get(route('reorder-suggestions.index'))->assertForbidden();
});

it('lets a Stock Clerk view reorder suggestions', function () {
    Livewire::actingAs($this->stockClerk)
        ->test(ReorderSuggestionsIndex::class)
        ->set('stock_location_id', $this->location->id)
        ->assertSee($this->item->name)
        ->assertSee($this->supplier->company_name);
});

it('creates a purchase order from selected reorder suggestions', function () {
    Livewire::actingAs($this->admin)
        ->test(ReorderSuggestionsIndex::class)
        ->set('stock_location_id', $this->location->id)
        ->set("quantities.{$this->item->id}", '24')
        ->set("unitCosts.{$this->item->id}", '5.00')
        ->call('createPurchaseOrder', $this->supplier->id)
        ->assertHasNoErrors();

    $po = PurchaseOrder::where('supplier_id', $this->supplier->id)->latest()->first();
    expect($po)->not->toBeNull()
        ->and($po->stock_location_id)->toBe($this->location->id)
        ->and($po->lines)->toHaveCount(1)
        ->and((string) $po->lines->first()->quantity_ordered)->toBe('24.000');
});

it('refuses to create a purchase order when nothing is selected', function () {
    Livewire::actingAs($this->admin)
        ->test(ReorderSuggestionsIndex::class)
        ->set('stock_location_id', $this->location->id)
        ->set("selected.{$this->item->id}", false)
        ->call('createPurchaseOrder', $this->supplier->id)
        ->assertHasErrors('lines');

    expect(PurchaseOrder::where('supplier_id', $this->supplier->id)->exists())->toBeFalse();
});
