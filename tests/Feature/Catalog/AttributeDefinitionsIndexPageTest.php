<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\AttributeDefinition;
use App\Domain\Catalog\Models\AttributeLink;
use App\Domain\Catalog\Models\AttributeValue;
use App\Domain\Catalog\Models\Item;
use App\Domain\Identity\Models\User;
use App\Livewire\Catalog\AttributeDefinitions\Index;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->owner = User::where('username', 'admin')->firstOrFail();
});

it('filters attributes by type hierarchy visibility and item usage', function () {
    $group = AttributeDefinition::create(['name' => 'Page Test Specifications', 'type' => 'group']);
    $definition = AttributeDefinition::create([
        'name' => 'Page Test Weight',
        'type' => 'decimal',
        'unit' => 'kg',
        'parent_id' => $group->id,
        'show_in_search' => true,
        'show_in_receipt' => true,
    ]);
    $value = AttributeValue::create(['attribute_definition_id' => $definition->id, 'value_decimal' => '2.5']);
    $item = Item::query()->firstOrFail();
    AttributeLink::create(['attribute_value_id' => $value->id, 'attributable_id' => $item->id, 'attributable_type' => $item->getMorphClass()]);

    Livewire::actingAs($this->owner)
        ->test(Index::class)
        ->set('search', 'kg')
        ->set('type', 'decimal')
        ->set('level', 'child')
        ->set('visibility', 'receipt')
        ->set('usage', 'used')
        ->assertViewHas('attributeDefinitions', fn (LengthAwarePaginator $definitions): bool => $definitions->total() === 1
            && $definitions->first()->is($definition)
            && (int) $definitions->first()->assignments_count === 1)
        ->assertViewHas('summary', fn (array $summary): bool => $summary['total'] === 1 && $summary['assignments'] === 1);
});

it('clears attribute listing filters', function () {
    Livewire::actingAs($this->owner)
        ->test(Index::class)
        ->set('search', 'weight')
        ->set('type', 'decimal')
        ->set('level', 'child')
        ->set('visibility', 'search')
        ->set('usage', 'unused')
        ->set('sort', 'usage')
        ->call('clearFilters')
        ->assertSet('search', '')
        ->assertSet('type', '')
        ->assertSet('level', '')
        ->assertSet('visibility', '')
        ->assertSet('usage', '')
        ->assertSet('sort', 'name');
});
