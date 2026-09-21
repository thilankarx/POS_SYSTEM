<?php

declare(strict_types=1);

use App\Domain\Giftcards\Actions\IssueGiftcardAction;
use App\Domain\Giftcards\Models\Giftcard;
use App\Domain\Identity\Models\User;
use App\Livewire\Giftcards\Form as GiftcardForm;
use App\Livewire\Giftcards\Index as GiftcardsIndex;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed();
    $this->admin = User::where('username', 'admin')->firstOrFail();
    $this->cashier = User::where('username', 'cashier')->firstOrFail();
});

it('lets a Cashier view gift cards but not issue one', function () {
    $this->actingAs($this->cashier)->get(route('giftcards.index'))->assertOk();
    $this->actingAs($this->cashier)->get(route('giftcards.create'))->assertForbidden();
});

it('lets an admin issue a gift card', function () {
    Livewire::actingAs($this->admin)
        ->test(GiftcardForm::class)
        ->set('initial_value', '25.00')
        ->call('issue')
        ->assertHasNoErrors();

    expect(Giftcard::where('initial_value', '25.00')->exists())->toBeTrue();
});

it('lets a Cashier view an existing gift card but not top it up', function () {
    $giftcard = app(IssueGiftcardAction::class)->execute('20.00');

    $this->actingAs($this->cashier)->get(route('giftcards.edit', $giftcard))->assertOk();

    Livewire::actingAs($this->cashier)
        ->test(GiftcardForm::class, ['giftcard' => $giftcard])
        ->set('topUpAmount', '10.00')
        ->call('topUp')
        ->assertForbidden();

    Livewire::actingAs($this->admin)
        ->test(GiftcardForm::class, ['giftcard' => $giftcard])
        ->set('topUpAmount', '10.00')
        ->call('topUp')
        ->assertHasNoErrors();

    expect((string) $giftcard->fresh()->balance->getAmount())->toBe('30.00');
});

it('lets an admin delete an untouched gift card', function () {
    $giftcard = app(IssueGiftcardAction::class)->execute('25.00');

    Livewire::actingAs($this->admin)
        ->test(GiftcardsIndex::class)
        ->call('delete', $giftcard->id);

    expect(Giftcard::find($giftcard->id))->toBeNull()
        ->and(Giftcard::withTrashed()->find($giftcard->id))->not->toBeNull();
});

it('refuses to delete a gift card whose balance has changed since issue', function () {
    $giftcard = app(IssueGiftcardAction::class)->execute('20.00');

    Livewire::actingAs($this->admin)
        ->test(GiftcardForm::class, ['giftcard' => $giftcard])
        ->set('topUpAmount', '10.00')
        ->call('topUp');

    Livewire::actingAs($this->admin)
        ->test(GiftcardsIndex::class)
        ->call('delete', $giftcard->id);

    expect(Giftcard::find($giftcard->id))->not->toBeNull();
});

it('denies deleting a gift card to a Cashier', function () {
    $giftcard = app(IssueGiftcardAction::class)->execute('25.00');

    Livewire::actingAs($this->cashier)
        ->test(GiftcardsIndex::class)
        ->call('delete', $giftcard->id)
        ->assertForbidden();
});
