<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Item;
use App\Domain\Catalog\Models\ItemKit;
use App\Domain\Identity\Models\User;
use App\Livewire\Catalog\ItemKits\Form;
use App\Livewire\Catalog\ItemKits\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->owner = User::where('username', 'admin')->firstOrFail();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
    $this->cola = Item::where('sku', 'BEV-COLA-330')->firstOrFail();
    $this->water = Item::where('sku', 'BEV-WATER-500')->firstOrFail();
});

it('lists and creates a kit with component items', function () {
    $this->actingAs($this->owner)
        ->get(route('item-kits.index'))
        ->assertOk();

    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('kit_number', 'KIT-001')
        ->set('name', 'Drinks Bundle')
        ->set('discount_value', '10')
        ->set('discount_type', 'percent')
        ->set('items', [
            ['item_id' => $this->cola->id, 'quantity' => '2'],
            ['item_id' => $this->water->id, 'quantity' => '1'],
        ])
        ->call('save')
        ->assertRedirect(route('item-kits.index'));

    $kit = ItemKit::where('kit_number', 'KIT-001')->firstOrFail();
    expect($kit->name)->toBe('Drinks Bundle')
        ->and($kit->items)->toHaveCount(2)
        ->and((string) $kit->items->firstWhere('id', $this->cola->id)->pivot->quantity)->toBe('2.000');
});

it('edits a kit, adding, removing, and changing component quantities', function () {
    $kit = ItemKit::create([
        'kit_number' => 'KIT-002',
        'name' => 'Original',
        'discount_value' => '0',
        'discount_type' => 'percent',
        'price_option' => 'kit',
        'print_option' => 'all',
    ]);
    $kit->items()->sync([
        $this->cola->id => ['quantity' => '1', 'sequence' => 0],
        $this->water->id => ['quantity' => '1', 'sequence' => 1],
    ]);

    Livewire::actingAs($this->owner)
        ->test(Form::class, ['itemKit' => $kit])
        ->set('items', [
            ['item_id' => $this->cola->id, 'quantity' => '5'],
        ])
        ->call('save')
        ->assertRedirect(route('item-kits.index'));

    $kit->refresh();
    expect($kit->items)->toHaveCount(1)
        ->and((string) $kit->items->first()->pivot->quantity)->toBe('5.000');
});

it('rejects a duplicate kit number', function () {
    ItemKit::create([
        'kit_number' => 'KIT-DUP',
        'name' => 'Existing',
        'discount_value' => '0',
        'discount_type' => 'percent',
        'price_option' => 'kit',
        'print_option' => 'all',
    ]);

    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('kit_number', 'KIT-DUP')
        ->set('name', 'New Kit')
        ->set('items', [['item_id' => $this->cola->id, 'quantity' => '1']])
        ->call('save')
        ->assertHasErrors('kit_number');
});

it('rejects the same item added twice to one kit', function () {
    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('kit_number', 'KIT-003')
        ->set('name', 'Bad Kit')
        ->set('items', [
            ['item_id' => $this->cola->id, 'quantity' => '1'],
            ['item_id' => $this->cola->id, 'quantity' => '2'],
        ])
        ->call('save')
        ->assertHasErrors('items');
});

it('rejects an empty component list', function () {
    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('kit_number', 'KIT-004')
        ->set('name', 'Empty Kit')
        ->set('items', [])
        ->call('save')
        ->assertHasErrors('items');
});

it('allows a cashier to view but not manage kits', function () {
    $this->actingAs($this->cashier)
        ->get(route('item-kits.index'))
        ->assertOk();

    $this->actingAs($this->cashier)
        ->get(route('item-kits.create'))
        ->assertForbidden();
});

it('soft-deletes a kit', function () {
    $kit = ItemKit::create([
        'kit_number' => 'KIT-005',
        'name' => 'To Delete',
        'discount_value' => '0',
        'discount_type' => 'percent',
        'price_option' => 'kit',
        'print_option' => 'all',
    ]);

    Livewire::actingAs($this->owner)
        ->test(Index::class)
        ->call('delete', $kit->id);

    expect(ItemKit::find($kit->id))->toBeNull()
        ->and(ItemKit::withTrashed()->find($kit->id))->not->toBeNull();
});
