<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\AttributeDefinition;
use App\Domain\Identity\Models\User;
use App\Livewire\Catalog\AttributeDefinitions\Form;
use App\Livewire\Catalog\AttributeDefinitions\Index;

beforeEach(function () {
    $this->seed();
    $this->owner = User::where('username', 'admin')->firstOrFail();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
});

it('lists and creates attribute definitions', function () {
    $this->actingAs($this->owner)
        ->get(route('attribute-definitions.index'))
        ->assertOk();

    Livewire\Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('name', 'Color')
        ->set('type', 'text')
        ->call('save')
        ->assertRedirect(route('attribute-definitions.index'));

    expect(AttributeDefinition::where('name', 'Color')->where('type', 'text')->exists())->toBeTrue();
});

it('edits an attribute definition', function () {
    $definition = AttributeDefinition::create(['name' => 'Size', 'type' => 'text']);

    Livewire\Livewire::actingAs($this->owner)
        ->test(Form::class, ['attributeDefinition' => $definition])
        ->set('name', 'Shoe Size')
        ->call('save')
        ->assertRedirect(route('attribute-definitions.index'));

    expect($definition->fresh()->name)->toBe('Shoe Size');
});

it('refuses an attribute definition as its own parent', function () {
    $definition = AttributeDefinition::create(['name' => 'Specs', 'type' => 'group']);

    Livewire\Livewire::actingAs($this->owner)
        ->test(Form::class, ['attributeDefinition' => $definition])
        ->set('parent_id', $definition->id)
        ->call('save')
        ->assertHasErrors('parent_id');
});

it('blocks users without attributes.manage from viewing or creating attributes', function () {
    $this->actingAs($this->cashier)->get(route('attribute-definitions.index'))->assertForbidden();
    $this->actingAs($this->cashier)->get(route('attribute-definitions.create'))->assertForbidden();
});

it('soft-deletes an attribute definition', function () {
    $definition = AttributeDefinition::create(['name' => 'Material', 'type' => 'text']);

    Livewire\Livewire::actingAs($this->owner)
        ->test(Index::class)
        ->call('delete', $definition->id);

    expect(AttributeDefinition::find($definition->id))->toBeNull()
        ->and(AttributeDefinition::withTrashed()->find($definition->id))->not->toBeNull();
});
