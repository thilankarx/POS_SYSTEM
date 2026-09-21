<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Livewire\Catalog\Items\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->owner = User::where('username', 'admin')->firstOrFail();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
    $this->stockClerk = User::factory()->create();
    $this->stockClerk->assignRole('Stock Clerk');
    $this->cola = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    $this->water = Item::where('sku', 'BEV-WATER-500')->firstOrFail();
    $this->bread = Item::where('sku', 'BAK-BREAD-WHT')->firstOrFail();
});

it('bulk-applies a category change to selected items only', function () {
    $category = Category::create(['name' => 'Bulk Target', 'slug' => 'bulk-target']);

    $component = Livewire::actingAs($this->owner)
        ->test(Index::class)
        ->set('selected', [$this->cola->id, $this->water->id])
        ->set('bulkCategoryApply', true)
        ->set('bulkCategoryId', $category->id)
        ->call('applyBulkEdit');

    expect($this->cola->fresh()->category_id)->toBe($category->id)
        ->and($this->water->fresh()->category_id)->toBe($category->id)
        ->and($this->bread->fresh()->category_id)->not->toBe($category->id)
        ->and($component->get('bulkEditStatus'))->toBe('2 item(s) updated.');
});

it('bulk-applies an active-status change', function () {
    Livewire::actingAs($this->owner)
        ->test(Index::class)
        ->set('selected', [$this->cola->id])
        ->set('bulkActiveApply', true)
        ->set('bulkActiveValue', false)
        ->call('applyBulkEdit');

    expect($this->cola->fresh()->is_active)->toBeFalse();
});

it('leaves untouched fields alone when their toggle is off', function () {
    $originalSupplierId = $this->cola->supplier_id;

    Livewire::actingAs($this->owner)
        ->test(Index::class)
        ->set('selected', [$this->cola->id])
        ->set('bulkActiveApply', true)
        ->set('bulkActiveValue', false)
        ->call('applyBulkEdit');

    expect($this->cola->fresh()->supplier_id)->toBe($originalSupplierId);
});

it('requires at least one field toggled on', function () {
    Livewire::actingAs($this->owner)
        ->test(Index::class)
        ->set('selected', [$this->cola->id])
        ->call('applyBulkEdit')
        ->assertHasErrors('bulkEdit');
});

it('allows a Stock Clerk (items.bulk_edit) to bulk-edit but denies a Cashier', function () {
    Livewire::actingAs($this->stockClerk)
        ->test(Index::class)
        ->set('selected', [$this->cola->id])
        ->set('bulkActiveApply', true)
        ->set('bulkActiveValue', false)
        ->call('applyBulkEdit');

    expect($this->cola->fresh()->is_active)->toBeFalse();

    Livewire::actingAs($this->cashier)
        ->test(Index::class)
        ->set('selected', [$this->water->id])
        ->set('bulkActiveApply', true)
        ->set('bulkActiveValue', false)
        ->call('applyBulkEdit')
        ->assertForbidden();
});
