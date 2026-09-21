<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\AttributeDefinition;
use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Livewire\Catalog\AttributeDefinitions\Form as AttributeForm;
use App\Livewire\Catalog\ItemKits\Form as ItemKitForm;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->owner = User::where('username', 'admin')->firstOrFail();
});

it('rejects a zero component quantity when creating an item kit', function () {
    $item = Item::query()->firstOrFail();

    Livewire::actingAs($this->owner)
        ->test(ItemKitForm::class)
        ->set('kit_number', 'ZERO-QTY-KIT')
        ->set('name', 'Zero quantity kit')
        ->set('items', [['item_id' => $item->id, 'quantity' => '0']])
        ->call('save')
        ->assertHasErrors('items.0.quantity');
});

it('clears value-only options when an attribute becomes a group', function () {
    Livewire::actingAs($this->owner)
        ->test(AttributeForm::class)
        ->set('unit', 'kg')
        ->set('show_in_search', true)
        ->set('show_in_receipt', true)
        ->set('type', AttributeDefinition::TYPE_GROUP)
        ->assertSet('unit', '')
        ->assertSet('show_in_search', false)
        ->assertSet('show_in_receipt', false);
});
