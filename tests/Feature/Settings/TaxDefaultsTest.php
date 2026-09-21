<?php

declare(strict_types=1);

use App\Domain\Identity\Models\User;
use App\Domain\Taxation\TaxEngine;
use App\Livewire\Settings\TaxDefaults;
use App\Settings\TaxSettings;
use Brick\Money\Money;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->owner = User::where('username', 'admin')->firstOrFail();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
});

it('denies the tax defaults page to a user without config.manage', function () {
    Livewire::actingAs($this->cashier)
        ->test(TaxDefaults::class)
        ->assertForbidden();
});

it('loads the current prices_include_tax value', function () {
    $settings = app(TaxSettings::class);
    $settings->prices_include_tax = true;
    $settings->save();

    Livewire::actingAs($this->owner)
        ->test(TaxDefaults::class)
        ->assertSet('prices_include_tax', true);
});

it('saves the toggle and TaxEngine::fromSettings reflects it', function () {
    Livewire::actingAs($this->owner)
        ->test(TaxDefaults::class)
        ->set('prices_include_tax', true)
        ->call('save')
        ->assertHasNoErrors();

    expect(app(TaxSettings::class)->prices_include_tax)->toBeTrue();

    $inclusiveTax = TaxEngine::fromSettings()->taxFor(Money::of('110.00', 'USD'), '10');
    expect((string) $inclusiveTax->getAmount())->toBe('10.00');
});
