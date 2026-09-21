<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Inventory\Models\StockLocation;
use App\Domain\Sales\Models\Sale;
use App\Livewire\Sales\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
    $this->main = StockLocation::where('code', 'MAIN')->firstOrFail();
    $this->warehouse = StockLocation::where('code', 'WH')->firstOrFail();
});

it('scopes sales to assigned locations and supports the new filters', function () {
    $mainSale = Sale::create([
        'number' => 'POS-SALES-PAGE-MAIN',
        'user_id' => $this->cashier->id,
        'stock_location_id' => $this->main->id,
        'sale_type' => Sale::TYPE_POS,
        'status' => Sale::STATUS_COMPLETED,
        'total' => '125.00',
        'sold_at' => now(),
    ]);
    $warehouseSale = Sale::create([
        'number' => 'POS-SALES-PAGE-WH',
        'user_id' => $this->cashier->id,
        'stock_location_id' => $this->warehouse->id,
        'sale_type' => Sale::TYPE_INVOICE,
        'status' => Sale::STATUS_COMPLETED,
        'total' => '250.00',
        'sold_at' => now(),
    ]);

    Livewire::actingAs($this->cashier)
        ->test(Index::class)
        ->assertViewHas('sales', fn ($sales) => $sales->contains('id', $mainSale->id)
            && ! $sales->contains('id', $warehouseSale->id))
        ->assertSee('125.00')
        ->set('type', Sale::TYPE_INVOICE)
        ->assertViewHas('sales', fn ($sales) => $sales->isEmpty())
        ->set('type', Sale::TYPE_POS)
        ->set('search', $mainSale->number)
        ->assertViewHas('sales', fn ($sales) => $sales->count() === 1
            && $sales->first()->is($mainSale))
        ->call('setRange', 'today')
        ->assertSet('from', today()->toDateString())
        ->assertSet('to', today()->toDateString())
        ->call('clearFilters')
        ->assertSet('search', '')
        ->assertSet('type', '')
        ->assertSet('sort', 'newest');
});
