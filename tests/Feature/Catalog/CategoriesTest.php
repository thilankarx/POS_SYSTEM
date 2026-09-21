<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Category;
use App\Domain\Identity\Models\User;
use App\Livewire\Catalog\Categories\Form;
use App\Livewire\Catalog\Categories\Index;

beforeEach(function () {
    $this->seed();
    $this->owner = User::where('username', 'admin')->firstOrFail();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
});

it('lists and creates categories', function () {
    $this->actingAs($this->owner)
        ->get(route('categories.index'))
        ->assertOk();

    Livewire\Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('name', 'Frozen Foods')
        ->call('save')
        ->assertRedirect(route('categories.index'));

    expect(Category::where('name', 'Frozen Foods')->where('slug', 'frozen-foods')->exists())->toBeTrue();
});

it('edits a category', function () {
    $category = Category::create(['name' => 'Snacks', 'slug' => 'snacks']);

    Livewire\Livewire::actingAs($this->owner)
        ->test(Form::class, ['category' => $category])
        ->set('name', 'Salty Snacks')
        ->call('save')
        ->assertRedirect(route('categories.index'));

    expect($category->fresh()->name)->toBe('Salty Snacks');
});

it('rejects a duplicate slug', function () {
    Category::create(['name' => 'Snacks', 'slug' => 'snacks']);

    Livewire\Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('name', 'Other')
        ->set('slug', 'snacks')
        ->call('save')
        ->assertHasErrors('slug');
});

it('refuses a category as its own parent', function () {
    $category = Category::create(['name' => 'Snacks', 'slug' => 'snacks']);

    Livewire\Livewire::actingAs($this->owner)
        ->test(Form::class, ['category' => $category])
        ->set('parent_id', $category->id)
        ->call('save')
        ->assertHasErrors('parent_id');
});

it('blocks users without items.manage from creating categories', function () {
    $this->actingAs($this->cashier)
        ->get(route('categories.create'))
        ->assertForbidden();
});

it('soft-deletes a category', function () {
    $category = Category::create(['name' => 'Snacks', 'slug' => 'snacks']);

    Livewire\Livewire::actingAs($this->owner)
        ->test(Index::class)
        ->call('delete', $category->id);

    expect(Category::find($category->id))->toBeNull()
        ->and(Category::withTrashed()->find($category->id))->not->toBeNull();
});
