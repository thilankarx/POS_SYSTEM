<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\AttributeDefinition;
use App\Domain\Catalog\Models\AttributeValue;
use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Livewire\Catalog\Items\Form;
use App\Livewire\Catalog\Items\Index;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->owner = User::where('username', 'admin')->firstOrFail();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
});

it('lists and creates items', function () {
    $this->actingAs($this->owner)
        ->get(route('items.index'))
        ->assertOk();

    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('name', 'Test Widget')
        ->call('save')
        ->assertRedirect(route('items.index'));

    $item = Item::where('name', 'Test Widget')->first();
    expect($item)->not->toBeNull()
        ->and($item->sku)->toMatch('/^GEN-\d{6}$/');
});

it('saves reorder level and reorder quantity', function () {
    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('name', 'Test Reorder Widget')
        ->set('reorder_level', '10')
        ->set('reorder_quantity', '24')
        ->call('save')
        ->assertRedirect(route('items.index'));

    $item = Item::where('name', 'Test Reorder Widget')->firstOrFail();
    expect((string) $item->reorder_level)->toBe('10.000')
        ->and((string) $item->reorder_quantity)->toBe('24.000');
});

it('edits an item', function () {
    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();

    Livewire::actingAs($this->owner)
        ->test(Form::class, ['item' => $item])
        ->set('name', 'Cola 330ml Renamed')
        ->call('save')
        ->assertRedirect(route('items.index'));

    expect($item->fresh()->name)->toBe('Cola 330ml Renamed');
});

it('assigns distinct sequential skus to items created back-to-back', function () {
    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('name', 'Sequential Widget One')
        ->call('save');

    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('name', 'Sequential Widget Two')
        ->call('save');

    $first = Item::where('name', 'Sequential Widget One')->firstOrFail();
    $second = Item::where('name', 'Sequential Widget Two')->firstOrFail();

    expect($first->sku)->not->toBe($second->sku);
});

it('auto-generates a primary barcode from the sku for a new item', function () {
    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('name', 'Barcode Default Widget')
        ->call('save')
        ->assertRedirect(route('items.index'));

    $item = Item::where('name', 'Barcode Default Widget')->firstOrFail();
    $barcode = $item->barcodes()->where('is_primary', true)->first();

    expect($barcode)->not->toBeNull()
        ->and($barcode->barcode)->toBe($item->sku);
});

it('lets the customer override the generated primary barcode', function () {
    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('name', 'Barcode Override Widget')
        ->set('barcode', '0123456789012')
        ->call('save')
        ->assertRedirect(route('items.index'));

    $item = Item::where('name', 'Barcode Override Widget')->firstOrFail();
    $barcode = $item->barcodes()->where('is_primary', true)->first();

    expect($barcode->barcode)->toBe('0123456789012');
});

it('still assigns a primary barcode when the customer clears the field', function () {
    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('name', 'Barcode Cleared Widget')
        ->set('barcode', '')
        ->call('save')
        ->assertRedirect(route('items.index'));

    $item = Item::where('name', 'Barcode Cleared Widget')->firstOrFail();
    $barcode = $item->barcodes()->where('is_primary', true)->first();

    expect($barcode)->not->toBeNull()
        ->and($barcode->barcode)->toBe($item->sku);
});

it('rejects a non-numeric price', function () {
    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('name', 'Bad Price Item')
        ->set('unit_price', 'abc')
        ->call('save')
        ->assertHasErrors('unit_price');
});

it('saves the serialized and expiry flags', function () {
    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('name', 'Flagged Widget')
        ->set('is_serialized', true)
        ->set('has_expiry', true)
        ->call('save')
        ->assertRedirect(route('items.index'));

    $item = Item::where('name', 'Flagged Widget')->firstOrFail();
    expect($item->is_serialized)->toBeTrue()
        ->and($item->has_expiry)->toBeTrue();
});

it('assigns a text and a decimal attribute to an item', function () {
    $color = AttributeDefinition::create(['name' => 'Color', 'type' => 'text']);
    $weight = AttributeDefinition::create(['name' => 'Weight', 'type' => 'decimal']);

    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('name', 'Attributed Widget')
        ->set('itemAttributes', [
            ['attribute_definition_id' => $color->id, 'value' => 'Red'],
            ['attribute_definition_id' => $weight->id, 'value' => '2.5'],
        ])
        ->call('save')
        ->assertRedirect(route('items.index'));

    $item = Item::where('name', 'Attributed Widget')->firstOrFail();
    $links = $item->attributeLinks()->with('value')->get();

    expect($links)->toHaveCount(2)
        ->and($links->firstWhere('value.attribute_definition_id', $color->id)->value->value_text)->toBe('Red')
        ->and((string) $links->firstWhere('value.attribute_definition_id', $weight->id)->value->value_decimal)->toBe('2.5000');
});

it('shares one attribute value across items with the same value (dedup)', function () {
    $color = AttributeDefinition::create(['name' => 'Color', 'type' => 'text']);

    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('name', 'Widget One')
        ->set('itemAttributes', [['attribute_definition_id' => $color->id, 'value' => 'Red']])
        ->call('save');

    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('name', 'Widget Two')
        ->set('itemAttributes', [['attribute_definition_id' => $color->id, 'value' => 'Red']])
        ->call('save');

    expect(AttributeValue::where('attribute_definition_id', $color->id)->where('value_text', 'Red')->count())->toBe(1);
});

it('removes an attribute link when edited to remove the row', function () {
    $color = AttributeDefinition::create(['name' => 'Color', 'type' => 'text']);
    $item = Item::where('sku', 'BEV-COLA-330')->firstOrFail();

    Livewire::actingAs($this->owner)
        ->test(Form::class, ['item' => $item])
        ->set('itemAttributes', [['attribute_definition_id' => $color->id, 'value' => 'Red']])
        ->call('save');

    expect($item->attributeLinks()->count())->toBe(1);

    Livewire::actingAs($this->owner)
        ->test(Form::class, ['item' => $item])
        ->set('itemAttributes', [])
        ->call('save');

    expect($item->attributeLinks()->count())->toBe(0);
});

it('rejects a non-numeric value for a decimal attribute', function () {
    $weight = AttributeDefinition::create(['name' => 'Weight', 'type' => 'decimal']);

    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('name', 'Bad Attr Widget')
        ->set('itemAttributes', [['attribute_definition_id' => $weight->id, 'value' => 'heavy']])
        ->call('save')
        ->assertHasErrors('itemAttributes.0.value');
});

it('blocks users without items.manage from creating items', function () {
    $this->actingAs($this->cashier)
        ->get(route('items.create'))
        ->assertForbidden();
});

it('soft-deletes an item without touching referencing rows', function () {
    $item = Item::where('sku', 'BAK-BREAD-WHT')->firstOrFail();

    Livewire::actingAs($this->owner)
        ->test(Index::class)
        ->call('delete', $item->id);

    expect(Item::find($item->id))->toBeNull()
        ->and(Item::withTrashed()->find($item->id))->not->toBeNull();
});
