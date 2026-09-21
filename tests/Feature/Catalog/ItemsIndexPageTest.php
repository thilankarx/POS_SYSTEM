<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Livewire\Catalog\Items\Index;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->owner = User::where('username', 'admin')->firstOrFail();
});

it('filters the catalog by category and stock type', function () {
    $item = Item::whereNotNull('category_id')->where('stock_type', Item::STOCK_TYPE_STOCKED)->firstOrFail();

    Livewire::actingAs($this->owner)
        ->test(Index::class)
        ->set('businessType', 'all')
        ->set('category', (string) $item->category_id)
        ->set('stockType', Item::STOCK_TYPE_STOCKED)
        ->assertViewHas('items', function (LengthAwarePaginator $items) use ($item): bool {
            return $items->total() > 0
                && $items->getCollection()->every(fn (Item $result) => $result->category_id === $item->category_id && $result->movesStock());
        });
});

it('finds an item by barcode and clears listing filters', function () {
    $item = Item::whereHas('barcodes')->with('barcodes')->firstOrFail();
    $barcode = $item->barcodes->firstOrFail()->barcode;

    Livewire::actingAs($this->owner)
        ->test(Index::class)
        ->set('businessType', 'all')
        ->set('search', $barcode)
        ->assertViewHas('items', fn (LengthAwarePaginator $items): bool => $items->total() === 1 && $items->first()->is($item))
        ->set('status', 'active')
        ->call('clearFilters')
        ->assertSet('search', '')
        ->assertSet('status', '')
        ->assertSet('sort', 'name');
});
