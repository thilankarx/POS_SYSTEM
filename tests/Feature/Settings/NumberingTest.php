<?php

declare(strict_types=1);

use App\Domain\Documents\DocumentNumberGenerator;
use App\Domain\Documents\Models\DocumentSequence;
use App\Domain\Identity\Models\User;
use App\Livewire\Settings\Numbering;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->owner = User::where('username', 'admin')->firstOrFail();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
});

it('denies the numbering page to a user without config.manage', function () {
    Livewire::actingAs($this->cashier)
        ->test(Numbering::class)
        ->assertForbidden();
});

it('loads config defaults for a sequence never used before', function () {
    Livewire::actingAs($this->owner)
        ->test(Numbering::class)
        ->assertSet('sequences.sale.prefix', 'POS')
        ->assertSet('sequences.sale.padding', 6)
        ->assertSet('sequences.sale.next_value', 1);
});

it('loads existing DocumentSequence values when a sequence is already in use', function () {
    DocumentSequence::create(['key' => 'sale', 'scope' => 'global', 'prefix' => 'SL', 'padding' => 4, 'next_value' => 42]);

    Livewire::actingAs($this->owner)
        ->test(Numbering::class)
        ->assertSet('sequences.sale.prefix', 'SL')
        ->assertSet('sequences.sale.padding', 4)
        ->assertSet('sequences.sale.next_value', 42);
});

it('saves a new prefix and the next generated number reflects it', function () {
    Livewire::actingAs($this->owner)
        ->test(Numbering::class)
        ->set('sequences.sale.prefix', 'SL')
        ->set('sequences.sale.padding', 4)
        ->set('sequences.sale.next_value', 100)
        ->call('save')
        ->assertHasNoErrors();

    $number = app(DocumentNumberGenerator::class)->next('sale');

    expect($number)->toBe('SL-0100');
});

it('requires a prefix', function () {
    Livewire::actingAs($this->owner)
        ->test(Numbering::class)
        ->set('sequences.sale.prefix', '')
        ->call('save')
        ->assertHasErrors(['sequences.sale.prefix' => 'required']);
});
