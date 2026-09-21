<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Livewire\Catalog\Categories\Index;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->owner = User::where('username', 'admin')->firstOrFail();
});

it('filters categories by hierarchy usage and code', function () {
    $parent = Category::create(['name' => 'Page Test Parent', 'slug' => 'page-test-parent', 'code' => 'PTP']);
    $child = Category::create(['name' => 'Page Test Child', 'slug' => 'page-test-child', 'code' => 'PTC', 'parent_id' => $parent->id]);
    Item::query()->firstOrFail()->update(['category_id' => $child->id]);

    Livewire::actingAs($this->owner)
        ->test(Index::class)
        ->set('search', 'PTC')
        ->set('level', 'child')
        ->set('usage', 'used')
        ->assertViewHas('categories', fn (LengthAwarePaginator $categories): bool => $categories->total() === 1 && $categories->first()->is($child));
});

it('clears category listing filters', function () {
    Livewire::actingAs($this->owner)
        ->test(Index::class)
        ->set('search', 'hardware')
        ->set('level', 'top')
        ->set('usage', 'empty')
        ->set('sort', 'items')
        ->call('clearFilters')
        ->assertSet('search', '')
        ->assertSet('level', '')
        ->assertSet('usage', '')
        ->assertSet('sort', 'name');
});
