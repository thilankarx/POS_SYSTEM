<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\ItemKit;
use App\Domain\Identity\Models\User;
use App\Livewire\Catalog\ItemKits\Index;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->owner = User::where('username', 'admin')->firstOrFail();
});

it('filters kits by component pricing discount and receipt output', function () {
    $item = Item::query()->firstOrFail();
    $kit = ItemKit::create([
        'kit_number' => 'FILTER-KIT-01',
        'name' => 'Filtered bundle',
        'discount_value' => '10',
        'discount_type' => 'percent',
        'price_option' => 'kit',
        'print_option' => 'kit_only',
    ]);
    $kit->items()->attach($item->id, ['quantity' => '1', 'sequence' => 0]);

    Livewire::actingAs($this->owner)
        ->test(Index::class)
        ->set('businessType', 'all')
        ->set('search', $item->sku)
        ->set('priceOption', 'kit')
        ->set('discount', 'discounted')
        ->set('printOption', 'kit_only')
        ->assertViewHas('itemKits', fn (LengthAwarePaginator $kits): bool => $kits->total() === 1 && $kits->first()->is($kit))
        ->assertViewHas('summary', fn (array $summary): bool => $summary['total'] === 1 && $summary['components'] === 1 && $summary['discounted'] === 1);
});

it('clears item kit listing filters', function () {
    Livewire::actingAs($this->owner)
        ->test(Index::class)
        ->set('search', 'bundle')
        ->set('priceOption', 'both')
        ->set('discount', 'none')
        ->set('printOption', 'kit_only')
        ->set('sort', 'components')
        ->call('clearFilters')
        ->assertSet('search', '')
        ->assertSet('priceOption', '')
        ->assertSet('discount', '')
        ->assertSet('printOption', '')
        ->assertSet('sort', 'name');
});
