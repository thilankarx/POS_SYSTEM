<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\SerialNumber;
use App\Domain\Inventory\Models\StockLocation;
use App\Livewire\Inventory\SerialNumbers\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->owner = User::where('username', 'admin')->firstOrFail();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
    $this->accountant = User::factory()->create();
    $this->accountant->assignRole('Accountant');
    $this->location = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
});

it('lists and searches serial numbers', function () {
    SerialNumber::create([
        'item_id' => $this->item->id,
        'serial' => 'SN-LIST-1',
        'stock_location_id' => $this->location->id,
        'status' => SerialNumber::STATUS_IN_STOCK,
    ]);
    SerialNumber::create([
        'item_id' => $this->item->id,
        'serial' => 'SN-LIST-2',
        'stock_location_id' => $this->location->id,
        'status' => SerialNumber::STATUS_SOLD,
    ]);

    $this->actingAs($this->owner)
        ->get(route('serial-numbers.index'))
        ->assertOk()
        ->assertSee('SN-LIST-1')
        ->assertSee('SN-LIST-2');

    Livewire::actingAs($this->owner)
        ->test(Index::class)
        ->set('search', 'SN-LIST-1')
        ->assertSee('SN-LIST-1')
        ->assertDontSee('SN-LIST-2');
});

it('filters serial numbers by status', function () {
    SerialNumber::create([
        'item_id' => $this->item->id,
        'serial' => 'SN-STATUS-1',
        'stock_location_id' => $this->location->id,
        'status' => SerialNumber::STATUS_IN_STOCK,
    ]);
    SerialNumber::create([
        'item_id' => $this->item->id,
        'serial' => 'SN-STATUS-2',
        'stock_location_id' => $this->location->id,
        'status' => SerialNumber::STATUS_SOLD,
    ]);

    Livewire::actingAs($this->owner)
        ->test(Index::class)
        ->set('status', 'sold')
        ->assertSee('SN-STATUS-2')
        ->assertDontSee('SN-STATUS-1');
});

it('allows a Cashier (inventory.view) to view serial numbers but denies a role without it', function () {
    $this->actingAs($this->cashier)
        ->get(route('serial-numbers.index'))
        ->assertOk();

    $this->actingAs($this->accountant)
        ->get(route('serial-numbers.index'))
        ->assertForbidden();
});
