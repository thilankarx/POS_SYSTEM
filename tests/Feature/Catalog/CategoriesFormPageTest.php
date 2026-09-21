<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Category;
use App\Domain\Identity\Models\User;
use App\Livewire\Catalog\Categories\Form;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->owner = User::where('username', 'admin')->firstOrFail();
});

it('previews the generated category identifiers', function () {
    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('name', 'Frozen Foods')
        ->assertViewHas('slugPreview', 'frozen-foods')
        ->assertViewHas('codePreview', 'FROZ');
});

it('validates an automatically generated code before saving', function () {
    Category::create(['name' => 'Plum One', 'slug' => 'plum-one', 'code' => 'PLUM']);

    Livewire::actingAs($this->owner)
        ->test(Form::class)
        ->set('name', 'Plum Two')
        ->call('save')
        ->assertHasErrors('code');

    expect(Category::where('slug', 'plum-two')->exists())->toBeFalse();
});
