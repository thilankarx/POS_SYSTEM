<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Crm\Models\Supplier;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Purchasing\Actions\ReceiveGoodsAction;
use App\Domain\Purchasing\Models\Receiving;
use App\Livewire\Purchasing\Receivings\Show;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->admin = User::where('username', 'admin')->firstOrFail();
    $this->stockClerk = User::factory()->create();
    $this->stockClerk->assignRole('Stock Clerk');
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
    $this->supplier = Supplier::firstOrFail();
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
});

it('lets a Stock Clerk view a receiving with its lines and totals', function () {
    $receiving = app(ReceiveGoodsAction::class)->execute(
        purchaseOrder: null,
        supplier: $this->supplier,
        location: $this->location,
        user: $this->admin,
        type: Receiving::TYPE_RECEIPT,
        lines: [[
            'item_id' => $this->item->id,
            'quantity' => '12',
            'unit_cost' => '0.75',
            'lot_number' => 'LOT-SHOW-1',
            'expires_on' => now()->addYear()->toDateString(),
        ]],
    );

    $this->actingAs($this->stockClerk)->get(route('receivings.show', $receiving))->assertOk();

    Livewire::actingAs($this->stockClerk)
        ->test(Show::class, ['receiving' => $receiving])
        ->assertSee($receiving->number)
        ->assertSee($this->item->name)
        ->assertSee('LOT-SHOW-1')
        ->assertSee((string) $receiving->total->getAmount());
});

it('forbids a user without receivings.view from opening a receiving', function () {
    $receiving = app(ReceiveGoodsAction::class)->execute(
        purchaseOrder: null,
        supplier: $this->supplier,
        location: $this->location,
        user: $this->admin,
        type: Receiving::TYPE_RECEIPT,
        lines: [['item_id' => $this->item->id, 'quantity' => '5', 'unit_cost' => '0.75', 'lot_number' => 'LOT-SHOW-2']],
    );

    $this->actingAs($this->cashier)->get(route('receivings.show', $receiving))->assertForbidden();
});
